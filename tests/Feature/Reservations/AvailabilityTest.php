<?php

namespace Tests\Feature\Reservations;

use App\Models\ClosedDate;
use App\Models\TrainerSchedule;
use Tests\Feature\ReservationTestCase;

class AvailabilityTest extends ReservationTestCase
{
    public function test_public_calendar_returns_fourteen_days_and_half_hour_slots(): void
    {
        $response = $this->getJson('/availability?start='.$this->bookingDate);

        $response->assertOk()
            ->assertJsonCount(14, 'days')
            ->assertJsonPath('days.0.date', $this->bookingDate)
            ->assertJsonPath('slots.'.$this->bookingDate.'.10:00.status', 'available')
            ->assertJsonPath('slots.'.$this->bookingDate.'.10:00.availableCount', 1);
        $this->assertContains('09:00', $response->json('times'));
        $this->assertContains('09:30', $response->json('times'));
    }

    public function test_closed_weekday_and_exceptional_closure_cannot_be_booked(): void
    {
        ClosedDate::create(['date' => $this->bookingDate, 'reason' => '設備点検']);

        $this->getJson('/availability?start='.$this->bookingDate)
            ->assertJsonPath('slots.'.$this->bookingDate.'.10:00.status', 'closed');
        $this->actingAs($this->client)
            ->post('/reservations', $this->bookingData())
            ->assertSessionHasErrors('time');

        $this->post('/reservations', $this->bookingData(['date' => '2030-01-14']))
            ->assertSessionHasErrors('time');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_entire_session_must_fit_the_trainers_shift(): void
    {
        TrainerSchedule::where('trainer_id', $this->trainer->id)
            ->where('day_of_week', 3)
            ->update(['end_time' => '10:30:00']);

        $this->getJson('/availability?'.http_build_query([
            'start' => $this->bookingDate,
            'trainer_id' => $this->trainer->id,
        ]))->assertJsonPath('slots.'.$this->bookingDate.'.10:00.status', 'full');

        $this->actingAs($this->client)
            ->post('/reservations', $this->bookingData())
            ->assertSessionHasErrors('time');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_previous_calendar_request_is_validated(): void
    {
        $this->getJson('/availability?start=not-a-date')->assertUnprocessable()->assertJsonValidationErrors('start');
    }
}
