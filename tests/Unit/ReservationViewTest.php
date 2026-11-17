<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\User;
use Tests\TestCase;

class ReservationViewTest extends TestCase
{
    public function test_utc_reservation_is_presented_on_the_correct_japanese_calendar_day(): void
    {
        $reservation = new Reservation([
            'reference' => 'FORM-TEST123',
            'starts_at' => '2030-01-08 16:30:00',
            'ends_at' => '2030-01-08 17:30:00',
            'status' => 'confirmed',
            'duration' => 60,
            'price' => 8800,
            'notes' => null,
        ]);
        $reservation->forceFill(['id' => 10]);
        $reservation->setRelation('trainer', (new Trainer(['name' => '山田']))->forceFill(['id' => 2]));
        $reservation->setRelation('user', (new User(['name' => '花子', 'email' => 'hanako@example.com']))->forceFill(['id' => 3]));

        $view = $reservation->toView();

        $this->assertSame('2030-01-09', $view['date']);
        $this->assertSame('01:30', $view['time']);
        $this->assertSame('02:30', $view['end_time']);
        $this->assertSame('2030-01-08T16:30:00+00:00', $view['starts_at']);
        $this->assertSame('confirmed', $view['status']);
        $this->assertSame('2030-01-08 16:30:00', $reservation->starts_at->format('Y-m-d H:i:s'));
    }
}
