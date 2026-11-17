<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_registration_creates_a_client_and_does_not_accept_an_injected_admin_role(): void
    {
        $this->post('/register', [
            'name' => '山田 花子',
            'email' => 'hanako@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'role' => 'admin',
        ])->assertRedirect();

        $user = User::where('email', 'hanako@example.com')->sole();
        $this->assertSame('client', $user->getRawOriginal('role'));
        $this->assertTrue(Hash::check('StrongPassword123!', $user->password));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_duplicate_email_and_unconfirmed_password(): void
    {
        $user = User::factory()->create();

        $this->post('/register', [
            'name' => '新規クライアント',
            'email' => $user->email,
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_client_can_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => Hash::make('StrongPassword123!')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
            'remember' => true,
        ])->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_wrong_password_does_not_authenticate_the_user(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_guest_cannot_book_or_read_private_reservations(): void
    {
        $this->get('/reservations')->assertRedirect('/login');
        $this->post('/reservations', [])->assertRedirect('/login');
    }

    public function test_login_is_temporarily_limited_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => Hash::make('StrongPassword123!')]);
        $credentials = ['email' => $user->email, 'password' => 'wrong-password'];
        foreach (range(1, 5) as $attempt) {
            $this->post('/login', $credentials)->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        try {
            $this->travel(61)->seconds();
            $this->post('/login', ['email' => $user->email, 'password' => 'StrongPassword123!'])
                ->assertSessionHasNoErrors()->assertRedirect();
            $this->assertAuthenticatedAs($user);
        } finally {
            $this->travelBack();
        }
    }

}
