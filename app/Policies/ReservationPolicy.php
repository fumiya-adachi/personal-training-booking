<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function view(User $user, Reservation $reservation): bool
    {
        return $user->isAdmin() || $user->id === $reservation->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $this->view($user, $reservation);
    }
}
