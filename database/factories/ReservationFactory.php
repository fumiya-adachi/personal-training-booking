<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::tomorrow(config('booking.timezone'))->setTime(10, 0)->utc();
        return [
            'reference' => 'FRM-'.strtoupper(Str::random(10)),
            'user_id' => User::factory(),
            'trainer_id' => Trainer::factory(),
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(config('booking.duration')),
            'status' => ReservationStatus::Confirmed,
            'duration' => config('booking.duration'),
            'price' => config('booking.price'),
            'notes' => null,
        ];
    }
}
