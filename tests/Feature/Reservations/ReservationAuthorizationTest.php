<?php

namespace Tests\Feature\Reservations;

use App\Models\Reservation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\ReservationTestCase;

class ReservationAuthorizationTest extends ReservationTestCase
{
    public function test_client_can_only_view_and_cancel_their_own_reservations(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData());
        $reservation = Reservation::sole();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->get('/reservations/'.$reservation->id)->assertForbidden();
        $this->actingAs($otherUser)->delete('/reservations/'.$reservation->id)->assertForbidden();
        $this->assertSame('confirmed', $reservation->fresh()->getRawOriginal('status'));

        $this->actingAs($this->client)->get('/reservations/'.$reservation->id)->assertOk();
        $this->actingAs($this->client)->delete('/reservations/'.$reservation->id)->assertRedirect('/reservations');
        $this->assertSame('cancelled', $reservation->fresh()->getRawOriginal('status'));
    }

    public function test_personal_list_does_not_expose_another_clients_reservation(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData());
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)->get('/reservations')->assertInertia(fn (Assert $page) => $page
            ->component('reservations/Index')
            ->has('reservations', 0));
    }

    public function test_cancelling_a_reservation_makes_its_time_available_again(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData());
        $reservation = Reservation::sole();
        $query = http_build_query(['start' => $this->bookingDate, 'trainer_id' => $this->trainer->id]);

        $this->getJson('/availability?'.$query)
            ->assertJsonPath('slots.'.$this->bookingDate.'.10:00.status', 'full');
        $this->delete('/reservations/'.$reservation->id)->assertSessionHasNoErrors();
        $this->getJson('/availability?'.$query)
            ->assertJsonPath('slots.'.$this->bookingDate.'.10:00.status', 'available');
    }

    public function test_admin_can_view_a_clients_reservation(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData());
        $reservation = Reservation::sole();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/reservations/'.$reservation->id)->assertOk();
    }

    public function test_a_started_session_cannot_be_cancelled(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData());
        $reservation = Reservation::sole();
        $this->travelTo(\Carbon\CarbonImmutable::parse('2030-01-09 01:00:00', 'UTC'));

        $this->delete('/reservations/'.$reservation->id)->assertSessionHasErrors('reservation');
        $this->assertSame('confirmed', $reservation->fresh()->getRawOriginal('status'));
    }

}
