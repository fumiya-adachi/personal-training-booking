<?php

namespace Tests\Feature\Reservations;

use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\ReservationTestCase;

class CreateReservationTest extends ReservationTestCase
{
    public function test_a_client_can_book_and_the_confirmation_mail_is_queued_after_commit(): void
    {
        $response = $this->actingAs($this->client)->post('/reservations', $this->bookingData());

        $reservation = Reservation::sole();
        $response->assertRedirect('/reservations/'.$reservation->id);
        $this->assertSame($this->client->id, $reservation->user_id);
        $this->assertSame($this->trainer->id, $reservation->trainer_id);
        $this->assertSame('2030-01-09 01:00:00', $reservation->getRawOriginal('starts_at'));
        $this->assertSame('2030-01-09 02:00:00', $reservation->getRawOriginal('ends_at'));
        $this->assertSame(60, $reservation->duration);
        $this->assertSame(8800, $reservation->price);
        $this->assertNotEmpty($reservation->reference);

        Mail::assertQueued(ReservationConfirmed::class, function (ReservationConfirmed $mail) use ($reservation) {
            return $mail->hasTo($this->client->email)
                && $mail->reservation->is($reservation)
                && $mail->afterCommit === true;
        });
        Mail::assertQueuedCount(1);
    }

    public function test_overlapping_session_is_rejected_but_an_adjacent_session_is_allowed(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData())->assertRedirect();
        $otherClient = User::factory()->create();

        $this->actingAs($otherClient)
            ->post('/reservations', $this->bookingData(['time' => '10:30']))
            ->assertSessionHasErrors('time');
        $this->assertDatabaseCount('reservations', 1);
        Mail::assertQueuedCount(1);

        $this->actingAs($otherClient)
            ->post('/reservations', $this->bookingData(['time' => '11:00']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertDatabaseCount('reservations', 2);
    }

    public function test_no_preference_assigns_an_available_trainer_when_the_first_is_full(): void
    {
        $otherTrainer = $this->createTrainer('空いているトレーナー');
        $this->actingAs($this->client)->post('/reservations', $this->bookingData())->assertRedirect();
        $otherClient = User::factory()->create();

        $this->actingAs($otherClient)
            ->post('/reservations', $this->bookingData(['trainer_id' => null]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('reservations', [
            'user_id' => $otherClient->id,
            'trainer_id' => $otherTrainer->id,
            'status' => 'confirmed',
        ]);
    }

    #[DataProvider('invalidBookingInputs')]
    public function test_invalid_booking_input_is_rejected(array $overrides, string $field): void
    {
        $this->actingAs($this->client)
            ->post('/reservations', $this->bookingData($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('reservations', 0);
        Mail::assertNothingQueued();
    }

    public static function invalidBookingInputs(): array
    {
        return [
            'date required' => [['date' => null], 'date'],
            'invalid calendar date' => [['date' => '2030-02-30'], 'date'],
            'past day' => [['date' => '2030-01-07'], 'date'],
            'outside booking horizon' => [['date' => '2030-05-01'], 'date'],
            'invalid slot interval' => [['time' => '10:15'], 'time'],
            'invalid clock value' => [['time' => '25:00'], 'time'],
            'unknown trainer' => [['trainer_id' => 999999], 'trainer_id'],
            'notes too long' => [['notes' => str_repeat('あ', 1001)], 'notes'],
        ];
    }

    public function test_inactive_trainer_cannot_be_booked(): void
    {
        $this->trainer->update(['is_active' => false]);
        $this->actingAs($this->client)
            ->post('/reservations', $this->bookingData())
            ->assertSessionHasErrors('trainer_id');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_session_must_fit_inside_business_hours(): void
    {
        $this->actingAs($this->client)
            ->post('/reservations', $this->bookingData(['time' => '18:30']))
            ->assertSessionHasErrors('time');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_request_cannot_override_the_price_or_duration(): void
    {
        $this->actingAs($this->client)
            ->post('/reservations', $this->bookingData(['price' => 1, 'duration' => 5]))
            ->assertSessionHasNoErrors();

        $reservation = Reservation::sole();
        $this->assertSame(8800, $reservation->price);
        $this->assertSame(60, $reservation->duration);
    }

    public function test_confirmation_email_contains_the_reference_and_local_time(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData());
        $reservation = Reservation::sole();

        $html = (new ReservationConfirmed($reservation))->render();
        $this->assertStringContainsString($reservation->reference, $html);
        $this->assertStringContainsString($this->trainer->name, $html);
        $this->assertStringContainsString('10:00', $html);
        $this->assertStringContainsString('11:00', $html);
    }

    public function test_the_same_client_cannot_book_overlapping_sessions_with_different_trainers(): void
    {
        $otherTrainer = $this->createTrainer('別のトレーナー');
        $this->actingAs($this->client)->post('/reservations', $this->bookingData())->assertRedirect();

        $this->post('/reservations', $this->bookingData(['trainer_id' => $otherTrainer->id, 'time' => '10:30']))
            ->assertSessionHasErrors('time');
        $this->assertDatabaseCount('reservations', 1);
        Mail::assertQueuedCount(1);
    }

}
