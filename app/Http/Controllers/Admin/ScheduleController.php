<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClosedDateRequest;
use App\Http\Requests\Admin\UpdateScheduleRequest;
use App\Models\BusinessHour;
use App\Models\ClosedDate;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\TrainerSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/schedules/Index', [
            'closedWeekdays' => collect(range(0, 6))->diff(BusinessHour::where('is_open', true)->pluck('day_of_week'))->values(),
            'trainers' => Trainer::orderBy('id')->get(),
            'schedules' => TrainerSchedule::orderBy('trainer_id')->orderBy('day_of_week')->get()->map(fn (TrainerSchedule $schedule) => [
                'id' => $schedule->id,
                'trainer_id' => $schedule->trainer_id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => substr($schedule->start_time, 0, 5),
                'end_time' => substr($schedule->end_time, 0, 5),
                'is_working' => (bool) $schedule->is_working,
            ]),
            'closedDates' => ClosedDate::orderBy('date')->get()->map(fn (ClosedDate $closedDate) => [
                'id' => $closedDate->id,
                'date' => CarbonImmutable::parse($closedDate->date)->format('Y-m-d'),
                'reason' => $closedDate->reason,
            ]),
        ]);
    }

    public function update(UpdateScheduleRequest $request, Trainer $trainer): RedirectResponse
    {
        DB::transaction(function () use ($request, $trainer): void {
            // Booking creation acquires the same trainer lock before checking availability.
            Trainer::whereKey($trainer->id)->lockForUpdate()->firstOrFail();
            $schedules = collect($request->validated('schedules'))->keyBy('day_of_week');
            $futureReservations = Reservation::where('trainer_id', $trainer->id)
                ->where('status', ReservationStatus::Confirmed)
                ->where('starts_at', '>', now('UTC'))->get();

            foreach ($futureReservations as $reservation) {
                $start = CarbonImmutable::parse($reservation->starts_at)->setTimezone('Asia/Tokyo');
                $end = CarbonImmutable::parse($reservation->ends_at)->setTimezone('Asia/Tokyo');
                $schedule = $schedules->get($start->dayOfWeek);
                if (! $schedule['is_working'] || $start->format('H:i') < $schedule['start_time'] || $end->format('H:i') > $schedule['end_time'] || ! $start->isSameDay($end)) {
                    throw ValidationException::withMessages(['schedules' => '既存の予約と重なる勤務変更はできません。先に該当予約をご確認ください。']);
                }
            }

            foreach ($schedules as $schedule) {
                TrainerSchedule::updateOrCreate(['trainer_id' => $trainer->id, 'day_of_week' => $schedule['day_of_week']], [
                    'start_time' => $schedule['start_time'],
                    'end_time' => $schedule['end_time'],
                    'is_working' => $schedule['is_working'],
                ]);
            }
        });

        return back()->with('success', '勤務スケジュールを更新しました。');
    }

    public function updateClosedWeekdays(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'closed_weekdays' => ['present', 'array', 'max:7'],
            'closed_weekdays.*' => ['required', 'integer', 'between:0,6', 'distinct'],
        ]);
        $closedDays = array_map('intval', $data['closed_weekdays']);

        DB::transaction(function () use ($closedDays): void {
            // Use the same locks as booking creation and individual closures.
            Trainer::orderBy('id')->lockForUpdate()->get();
            $reservations = Reservation::where('status', ReservationStatus::Confirmed)
                ->where('ends_at', '>', now('UTC'))->get();
            foreach ($reservations as $reservation) {
                $day = CarbonImmutable::parse($reservation->starts_at)->setTimezone(config('booking.timezone'))->dayOfWeek;
                if (in_array($day, $closedDays, true)) {
                    throw ValidationException::withMessages(['closed_weekdays' => '指定した曜日には確定済みの予約があります。先に該当予約をご確認ください。']);
                }
            }
            foreach (range(0, 6) as $day) {
                $hours = BusinessHour::firstOrNew(['day_of_week' => $day], ['open_time' => '09:00', 'close_time' => '19:00']);
                $hours->is_open = ! in_array($day, $closedDays, true);
                $hours->save();
            }
        });

        return back()->with('success', '定休日を更新しました。');
    }

    public function storeClosedDate(StoreClosedDateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            Trainer::orderBy('id')->lockForUpdate()->get();
            $start = CarbonImmutable::parse($request->validated('date'), 'Asia/Tokyo')->startOfDay();
            if (Reservation::where('status', ReservationStatus::Confirmed)->where('starts_at', '>=', $start->utc())->where('starts_at', '<', $start->addDay()->utc())->exists()) {
                throw ValidationException::withMessages(['date' => 'この日には確定済みの予約があります。先に該当予約をご確認ください。']);
            }
            // Recheck under the trainer lock so concurrent closures return a validation error.
            if (ClosedDate::whereDate('date', $request->validated('date'))->exists()) {
                throw ValidationException::withMessages(['date' => 'この日付は既に休業日として登録されています。']);
            }
            ClosedDate::create($request->validated());
        });

        return back()->with('success', '休業日を登録しました。');
    }

    public function destroyClosedDate(ClosedDate $closedDate): RedirectResponse
    {
        $closedDate->delete();

        return back()->with('success', '休業日を削除しました。');
    }
}
