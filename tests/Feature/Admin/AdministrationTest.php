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
            ->get('/admin/reservations?'.http_build_query(['date' => $this->bookingDate, 'status' => 'confirmed']))
            ->assertInertia(fn (Assert $page) => $page->component('admin/reservations/Index')
                ->has('reservations', 1)
                ->where('reservations.0.id', $reservation->id)
                ->where('filters.date', $this->bookingDate)
                ->where('filters.status', 'confirmed'));

        $this->get('/admin/reservations?'.http_build_query(['date' => '2030-01-10', 'status' => 'confirmed']))
            ->assertInertia(fn (Assert $page) => $page->has('reservations', 0));
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
