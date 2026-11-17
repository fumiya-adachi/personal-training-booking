<?php

namespace Tests\Feature\Reservations;

use App\Models\BusinessHour;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\TrainerSchedule;
use App\Models\User;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConfirmationQueueTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2030-01-08 00:00:00', 'UTC'));
        // Use the real database queue: MailFake would skip queue transaction handling.
        config(['queue.default' => 'database', 'queue.connections.database.after_commit' => false]);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    public function test_confirmation_is_not_queued_until_the_outer_transaction_commits(): void
    {
        [$user, $data] = $this->fixture();

        DB::beginTransaction();
        app(ReservationService::class)->create($user, $data);
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();

        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_rolling_back_the_outer_transaction_discards_the_booking_and_its_confirmation(): void
    {
        [$user, $data] = $this->fixture();

        DB::beginTransaction();
        app(ReservationService::class)->create($user, $data);
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();

        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['role' => 'client']);
        $trainer = Trainer::create(['name' => '山田', 'specialty' => '筋力アップ', 'bio' => '', 'color' => '#ef786c', 'is_active' => true]);
        BusinessHour::create(['day_of_week' => 3, 'open_time' => '09:00', 'close_time' => '19:00', 'is_open' => true]);
        TrainerSchedule::create(['trainer_id' => $trainer->id, 'day_of_week' => 3, 'start_time' => '09:00', 'end_time' => '19:00', 'is_working' => true]);

        return [$user, ['date' => '2030-01-09', 'time' => '10:00', 'trainer_id' => $trainer->id]];
    }
}
