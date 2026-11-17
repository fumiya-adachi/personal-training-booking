<?php

namespace Tests\Feature\Admin;

use App\Models\ClosedDate;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\TrainerSchedule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\ReservationTestCase;

class AdministrationTest extends ReservationTestCase
{
    public function test_client_cannot_access_any_admin_area_or_change_trainers(): void
    {
        $this->actingAs($this->client);
        foreach (['reservations', 'trainers', 'schedules'] as $page) {
            $this->get('/admin/'.$page)->assertForbidden();
        }
        $this->post('/admin/trainers', $this->trainerData())->assertForbidden();
        $this->patch('/admin/trainers/'.$this->trainer->id, $this->trainerData())->assertForbidden();
        $this->put('/admin/schedules/'.$this->trainer->id, ['schedules' => $this->week()])->assertForbidden();
        $this->post('/admin/closed-dates', ['date' => '2030-01-10', 'reason' => '臨時休業'])->assertForbidden();
        $this->assertDatabaseCount('trainers', 1);
    }

    public function test_admin_can_add_and_update_a_trainer_with_a_complete_weekly_schedule(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/trainers', $this->trainerData())
            ->assertSessionHasNoErrors()->assertRedirect();
        $trainer = Trainer::where('name', '佐藤トレーナー')->sole();
        $this->assertSame(7, TrainerSchedule::where('trainer_id', $trainer->id)->count());

        $this->patch('/admin/trainers/'.$trainer->id, $this->trainerData(['name' => '佐藤 健', 'is_active' => false]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('trainers', ['id' => $trainer->id, 'name' => '佐藤 健', 'is_active' => false]);
    }

    public function test_trainer_and_schedule_input_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/trainers', $this->trainerData(['name' => '', 'color' => 'javascript:alert(1)']))
            ->assertSessionHasErrors(['name', 'color']);

        $invalidWeek = $this->week();
        $invalidWeek[3]['end_time'] = '08:00';
        $this->put('/admin/schedules/'.$this->trainer->id, ['schedules' => $invalidWeek])
            ->assertSessionHasErrors();
        $this->assertDatabaseCount('trainers', 1);
    }

    public function test_admin_can_change_the_week_and_create_and_remove_a_closure(): void
    {
        $week = $this->week();
        $week[3]['start_time'] = '10:00';
        $week[3]['end_time'] = '17:00';
        $this->actingAs($this->admin())
            ->put('/admin/schedules/'.$this->trainer->id, ['schedules' => $week])
            ->assertSessionHasNoErrors()->assertRedirect();
        $schedule = TrainerSchedule::where('trainer_id', $this->trainer->id)->where('day_of_week', 3)->sole();
        $this->assertSame('10:00', substr($schedule->start_time, 0, 5));
        $this->assertSame('17:00', substr($schedule->end_time, 0, 5));

        $this->post('/admin/closed-dates', ['date' => '2030-01-10', 'reason' => '設備点検'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $closedDate = ClosedDate::sole();
        $this->assertSame('設備点検', $closedDate->reason);
        $this->delete('/admin/closed-dates/'.$closedDate->id)->assertRedirect();
        $this->assertDatabaseCount('closed_dates', 0);
    }

    public function test_admin_cannot_make_a_schedule_or_closure_that_invalidates_a_confirmed_booking(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData());
        $this->assertDatabaseCount('reservations', 1);
        $week = $this->week();
        $week[3]['is_working'] = false;

        $this->actingAs($this->admin())
            ->put('/admin/schedules/'.$this->trainer->id, ['schedules' => $week])
            ->assertSessionHasErrors('schedules');
        $this->post('/admin/closed-dates', ['date' => $this->bookingDate, 'reason' => '臨時休業'])
            ->assertSessionHasErrors('date');
        $this->assertDatabaseCount('closed_dates', 0);
        $this->assertTrue(TrainerSchedule::where('trainer_id', $this->trainer->id)->where('day_of_week', 3)->sole()->is_working);
    }

    public function test_admin_reservation_list_can_filter_by_date_and_status(): void
    {
        $this->actingAs($this->client)->post('/reservations', $this->bookingData());
        $reservation = Reservation::sole();
        $this->actingAs($this->admin())
            ->getJson('/api/admin/reservations?'.http_build_query(['date' => $this->bookingDate, 'status' => 'confirmed']))
            ->assertOk()->assertJsonCount(1, 'reservations')
            ->assertJsonPath('reservations.0.id', $reservation->id)
            ->assertJsonPath('filters.date', $this->bookingDate)
            ->assertJsonPath('filters.status', 'confirmed');

        $this->getJson('/api/admin/reservations?'.http_build_query(['date' => '2030-01-10', 'status' => 'confirmed']))
            ->assertOk()->assertJsonCount(0, 'reservations');
    }

    public function test_admin_can_filter_reservations_by_trainer_and_combine_filters(): void
    {
        $other = $this->createTrainer('別のトレーナー');
        $this->actingAs($this->client)->post('/reservations', $this->bookingData())->assertSessionHasNoErrors();
        $reservation = Reservation::sole();
        $this->post('/reservations', $this->bookingData(['trainer_id' => $other->id, 'time' => '12:00']))->assertSessionHasNoErrors();
        $this->trainer->update(['is_active' => false]);

        $this->actingAs($this->admin());
        foreach ([
            ['trainer_id' => $this->trainer->id],
            ['trainer_id' => $this->trainer->id, 'date' => $this->bookingDate, 'status' => 'confirmed'],
        ] as $filters) {
            $this->getJson('/api/admin/reservations?'.http_build_query($filters))
                ->assertOk()->assertJsonCount(1, 'reservations')
                ->assertJsonPath('reservations.0.id', $reservation->id)
                ->assertJsonPath('filters.trainer_id', (string) $this->trainer->id);
        }

        $this->getJson('/api/admin/reservations?'.http_build_query(['trainer_id' => $this->trainer->id, 'status' => 'cancelled']))
            ->assertOk()->assertJsonCount(0, 'reservations');
        $this->getJson('/api/admin/reservations?trainer_id=')
            ->assertOk()->assertJsonCount(2, 'reservations')->assertJsonPath('filters.trainer_id', '');
    }

    public function test_admin_trainer_filter_rejects_invalid_values(): void
    {
        $this->actingAs($this->admin());
        foreach (['invalid', '999999'] as $value) {
            $this->getJson('/api/admin/reservations?trainer_id='.$value)
                ->assertUnprocessable()->assertJsonValidationErrors('trainer_id');
        }
    }

    public function test_reservation_api_requires_an_admin_and_returns_json_errors(): void
    {
        $this->get('/api/admin/reservations')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->actingAs($this->client)->getJson('/api/admin/reservations')->assertForbidden();
        $this->actingAs($this->admin())->getJson('/api/admin/reservations?date=invalid&status=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors(['date', 'status']);
    }

    public function test_admin_page_only_supplies_page_metadata(): void
    {
        $this->actingAs($this->admin())->get('/admin/reservations')
            ->assertInertia(fn (Assert $page) => $page->component('admin/reservations/Index')
                ->has('stats')->has('trainers')->missing('reservations'));
    }

    public function test_admin_can_change_weekly_closures_and_remove_them(): void
    {
        $this->actingAs($this->admin())->put('/admin/closed-weekdays', ['closed_weekdays' => [3, 5]])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('business_hours', ['day_of_week' => 1, 'is_open' => true]);
        $this->assertDatabaseHas('business_hours', ['day_of_week' => 3, 'is_open' => false]);
        $this->get('/admin/schedules')->assertInertia(fn (Assert $page) => $page->where('closedWeekdays', [3, 5]));
        $calendar = app(\App\Services\AvailabilityService::class)->calendar($this->bookingDate);
        $this->assertTrue($calendar['days'][0]['closed']);
        $this->assertSame('closed', $calendar['slots'][$this->bookingDate]['10:00']['status']);
        $this->actingAs($this->client)->post('/reservations', $this->bookingData())->assertSessionHasErrors();
        $this->assertDatabaseCount('reservations', 0);

        $this->actingAs($this->admin())->put('/admin/closed-weekdays', ['closed_weekdays' => []])
            ->assertSessionHasNoErrors();
        $calendar = app(\App\Services\AvailabilityService::class)->calendar($this->bookingDate);
        $this->assertFalse($calendar['days'][0]['closed']);
        $this->assertSame('available', $calendar['slots'][$this->bookingDate]['10:00']['status']);
        $this->assertSame(7, \App\Models\BusinessHour::where('is_open', true)->count());
    }

    public function test_weekly_closures_protect_existing_reservations_and_validate_permissions_and_input(): void
    {
        $this->actingAs($this->client)->put('/admin/closed-weekdays', ['closed_weekdays' => [3]])->assertForbidden();
        $this->post('/reservations', $this->bookingData())->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->put('/admin/closed-weekdays', ['closed_weekdays' => [3, 5]])
            ->assertSessionHasErrors('closed_weekdays');
        $this->assertDatabaseHas('business_hours', ['day_of_week' => 3, 'is_open' => true]);
        $this->assertDatabaseHas('business_hours', ['day_of_week' => 5, 'is_open' => true]);
        foreach ([[], ['closed_weekdays' => [7]], ['closed_weekdays' => [2, 2]], ['closed_weekdays' => 'invalid']] as $data) {
            $this->put('/admin/closed-weekdays', $data)->assertSessionHasErrors();
        }
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function trainerData(array $overrides = []): array
    {
        return array_replace([
            'name' => '佐藤トレーナー',
            'specialty' => 'ボディメイク',
            'bio' => '初心者の方も安心してご相談ください。',
            'color' => '#c8a99b',
            'is_active' => true,
        ], $overrides);
    }

    private function week(): array
    {
        return array_map(fn (int $day) => [
            'day_of_week' => $day,
            'start_time' => '09:00',
            'end_time' => '19:00',
            'is_working' => $day !== 1,
        ], range(0, 6));
    }
}
