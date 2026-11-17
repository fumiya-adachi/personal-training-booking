<?php

namespace Tests\Feature;

use App\Models\BusinessHour;
use App\Models\Trainer;
use App\Models\TrainerSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

abstract class ReservationTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $client;

    protected Trainer $trainer;

    protected string $bookingDate = '2030-01-09';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2030-01-08 00:00:00', 'UTC'));
        Mail::fake();
        $this->client = User::factory()->create(['role' => 'client']);
        $this->trainer = $this->createTrainer('山田トレーナー');

        foreach (range(0, 6) as $day) {
            BusinessHour::create([
                'day_of_week' => $day,
                'open_time' => '09:00:00',
                'close_time' => '19:00:00',
                'is_open' => $day !== 1,
            ]);
        }
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    protected function createTrainer(string $name, bool $active = true): Trainer
    {
        $trainer = Trainer::create([
            'name' => $name,
            'specialty' => '筋力アップ',
            'bio' => 'お客様に合わせたトレーニングを行います。',
            'color' => '#ef786c',
            'is_active' => $active,
        ]);

        foreach (range(0, 6) as $day) {
            TrainerSchedule::create([
                'trainer_id' => $trainer->id,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '19:00:00',
                'is_working' => $day !== 1,
            ]);
        }

        return $trainer;
    }

    protected function bookingData(array $overrides = []): array
    {
        return array_replace([
            'date' => $this->bookingDate,
            'time' => '10:00',
            'trainer_id' => $this->trainer->id,
            'notes' => '肩こりを改善したいです。',
        ], $overrides);
    }
}
