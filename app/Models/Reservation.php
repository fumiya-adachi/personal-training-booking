<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = ['reference', 'user_id', 'trainer_id', 'starts_at', 'ends_at', 'status', 'notes', 'duration', 'price'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'status' => ReservationStatus::class, 'duration' => 'integer', 'price' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(Trainer::class);
    }

    public function toView(): array
    {
        $this->loadMissing(['trainer', 'user']);
        $start = $this->starts_at->setTimezone(config('booking.timezone'));
        $end = $this->ends_at->setTimezone(config('booking.timezone'));

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'date' => $start->format('Y-m-d'),
            'time' => $start->format('H:i'),
            'end_time' => $end->format('H:i'),
            'starts_at' => $this->starts_at->toIso8601String(),
            'trainer' => ['id' => $this->trainer->id, 'name' => $this->trainer->name],
            'client' => ['id' => $this->user->id, 'name' => $this->user->name, 'email' => $this->user->email],
            'status' => $this->status->value,
            'notes' => $this->notes,
            'duration' => $this->duration,
            'price' => $this->price,
        ];
    }
}
