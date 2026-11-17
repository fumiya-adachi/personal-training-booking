<?php

namespace App\Policies;

use App\Models\TrainerSchedule;
use App\Models\User;

class TrainerSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, TrainerSchedule $schedule): bool
    {
        return $user->isAdmin();
    }
}
