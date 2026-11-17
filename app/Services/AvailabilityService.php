<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\BusinessHour;
use App\Models\ClosedDate;
use App\Models\Reservation;
use App\Models\Trainer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function calendar(string $start, ?int $trainerId = null): array
    {
        $timezone = config('booking.timezone');
        $first = CarbonImmutable::parse($start, $timezone)->startOfDay();
        $last = $first->addDays(13);
        $context = $this->context($first->utc(), $last->endOfDay()->utc(), $trainerId);
        $now = CarbonImmutable::now('UTC');
        $horizon = CarbonImmutable::today($timezone)->addDays(config('booking.horizon_days'))->endOfDay();
        $hours = $context['hours'];
        $opens = $hours->where('is_open', true)->pluck('open_time')->map(fn ($time) => substr($time, 0, 5));
        $closes = $hours->where('is_open', true)->pluck('close_time')->map(fn ($time) => substr($time, 0, 5));
        $firstTime = $opens->min() ?? '09:00';
        $lastTime = $closes->max() ?? '19:00';
        $times = [];
        for ($time = $first->setTimeFromTimeString($firstTime); $time->format('H:i') < $lastTime; $time = $time->addMinutes(config('booking.slot_minutes'))) {
            $times[] = $time->format('H:i');
        }

        $days = [];
        $slots = [];
        for ($offset = 0; $offset < 14; $offset++) {
            $day = $first->addDays($offset);
            $date = $day->format('Y-m-d');
            $hoursForDay = $hours->get($day->dayOfWeek);
            $closed = ! $hoursForDay?->is_open || $context['closedDates']->contains($date);
            $days[] = ['date' => $date, 'day' => $day->day, 'weekday' => $day->dayOfWeek, 'closed' => $closed];
            $slots[$date] = [];
            foreach ($times as $time) {
                $startAt = $day->setTimeFromTimeString($time)->utc();
                $endAt = $startAt->addMinutes(config('booking.duration'));
                $count = 0;
                if ($closed || ! $this->fitsHours($startAt, $endAt, $hoursForDay?->open_time, $hoursForDay?->close_time) || $startAt->setTimezone($timezone)->greaterThan($horizon)) {
                    $status = 'closed';
                } elseif ($startAt->lessThanOrEqualTo($now)) {
                    $status = 'past';
                } else {
                    $count = $this->availableFromContext($startAt, $context)->count();
                    $status = $count > 0 ? 'available' : 'full';
                }
                $slots[$date][$time] = ['status' => $status, 'availableCount' => $count];
            }
        }

        return ['start' => $first->format('Y-m-d'), 'end' => $last->format('Y-m-d'), 'days' => $days, 'times' => $times, 'slots' => $slots];
    }

    public function availableTrainers(CarbonImmutable $start, ?int $trainerId = null, ?int $ignoreReservationId = null): Collection
    {
        $start = $start->utc();
        $local = $start->setTimezone(config('booking.timezone'));
        $today = CarbonImmutable::today(config('booking.timezone'));
        if ($start->lessThanOrEqualTo(CarbonImmutable::now('UTC')) || $local->startOfDay()->lessThan($today) || $local->startOfDay()->greaterThan($today->addDays(config('booking.horizon_days'))) || $local->minute % config('booking.slot_minutes') !== 0 || $local->second !== 0) {
            return collect();
        }
        $context = $this->context($start, $start->addMinutes(config('booking.duration')), $trainerId, $ignoreReservationId);

        return $this->availableFromContext($start, $context);
    }

    private function context(CarbonImmutable $start, CarbonImmutable $end, ?int $trainerId, ?int $ignoreReservationId = null): array
    {
        return [
            'trainers' => Trainer::query()->where('is_active', true)->when($trainerId !== null, fn ($query) => $query->whereKey($trainerId))->with('schedules')->orderBy('id')->get(),
            'hours' => BusinessHour::all()->keyBy('day_of_week'),
            'closedDates' => ClosedDate::query()->whereDate('date', '>=', $start->setTimezone(config('booking.timezone'))->format('Y-m-d'))->whereDate('date', '<=', $end->setTimezone(config('booking.timezone'))->format('Y-m-d'))->get()->map(fn ($closed) => $closed->date->format('Y-m-d')),
            'reservations' => Reservation::query()->where('status', ReservationStatus::Confirmed)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->when($ignoreReservationId, fn ($query) => $query->where('id', '!=', $ignoreReservationId))->get()->groupBy('trainer_id'),
        ];
    }

    private function availableFromContext(CarbonImmutable $start, array $context): Collection
    {
        $end = $start->addMinutes(config('booking.duration'));
        $local = $start->setTimezone(config('booking.timezone'));
        $hours = $context['hours']->get($local->dayOfWeek);
        if (! $hours?->is_open || $context['closedDates']->contains($local->format('Y-m-d')) || ! $this->fitsHours($start, $end, $hours->open_time, $hours->close_time)) {
            return collect();
        }

        return $context['trainers']->filter(function (Trainer $trainer) use ($context, $local, $start, $end) {
            $schedule = $trainer->schedules->firstWhere('day_of_week', $local->dayOfWeek);
            if (! $schedule?->is_working || ! $this->fitsHours($start, $end, $schedule->start_time, $schedule->end_time)) {
                return false;
            }
            return ! $context['reservations']->get($trainer->id, collect())->contains(fn (Reservation $reservation) => $reservation->starts_at->lessThan($end) && $reservation->ends_at->greaterThan($start));
        })->values();
    }

    private function fitsHours(CarbonImmutable $start, CarbonImmutable $end, ?string $open, ?string $close): bool
    {
        if ($open === null || $close === null) {
            return false;
        }
        $localStart = $start->setTimezone(config('booking.timezone'));
        $localEnd = $end->setTimezone(config('booking.timezone'));

        return $localStart->isSameDay($localEnd)
            && $localStart->format('H:i:s') >= $this->normalizeTime($open)
            && $localEnd->format('H:i:s') <= $this->normalizeTime($close);
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
