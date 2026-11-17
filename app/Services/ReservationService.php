<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use App\Models\Trainer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function __construct(private AvailabilityService $availability) {}

    public function create(User $user, array $data): Reservation
    {
        return DB::transaction(function () use ($user, $data) {
            \Log::debug('Creating reservation for user '.$user->id.' with data: '.json_encode($data));
            // Every booking locks the client first, then trainer rows in ascending order.
            // All writers (including schedule changes) use these trainer locks.
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            Trainer::query()->where('is_active', true)->orderBy('id')->lockForUpdate()->get();

            $start = CarbonImmutable::createFromFormat('!Y-m-d H:i', $data['date'].' '.$data['time'], config('booking.timezone'))->utc();
            $end = $start->addMinutes(config('booking.duration'));
            $overlap = Reservation::query()->where('user_id', $user->id)->where('status', ReservationStatus::Confirmed)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists();
            if ($overlap) {
                throw ValidationException::withMessages(['time' => 'この時間帯にはすでにご自身の予約があります。']);
            }
            $available = $this->availability->availableTrainers($start, isset($data['trainer_id']) ? (int) $data['trainer_id'] : null);
            if ($available->isEmpty()) {
                throw ValidationException::withMessages(['time' => '選択した時間は予約できません。空き状況を確認し、別の時間をお選びください。']);
            }
            $trainer = $available->random();
            $reservation = Reservation::create([
                'reference' => 'FRM-'.strtoupper(Str::random(10)),
                'user_id' => $user->id,
                'trainer_id' => $trainer->id,
                'starts_at' => $start,
                'ends_at' => $end,
                'status' => ReservationStatus::Confirmed,
                'notes' => $data['notes'] ?? null,
                'duration' => config('booking.duration'),
                'price' => config('booking.price'),
            ]);
            Mail::to($user->email)->queue(new ReservationConfirmed($reservation));

            return $reservation;
        }, 3);
    }

    public function cancel(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            // Take the same locks as create so a new booking sees the final state.
            User::query()->whereKey($reservation->user_id)->lockForUpdate()->firstOrFail();
            Trainer::query()->whereKey($reservation->trainer_id)->lockForUpdate()->firstOrFail();
            $locked = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ReservationStatus::Confirmed || $locked->starts_at->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
                throw ValidationException::withMessages(['reservation' => '開始済み、またはキャンセル済みの予約は変更できません。']);
            }
            $locked->update(['status' => ReservationStatus::Cancelled]);
        }, 3);
    }
}
