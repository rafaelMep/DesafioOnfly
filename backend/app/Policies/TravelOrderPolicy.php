<?php

namespace App\Policies;

use App\Models\TravelOrder;
use App\Models\User;

class TravelOrderPolicy
{
    public function view(User $user, TravelOrder $travelOrder): bool
    {
        return $user->is_admin || $travelOrder->user_id === $user->id;
    }

    public function updateStatus(User $user, TravelOrder $travelOrder): bool
    {
        return $user->is_admin && $travelOrder->user_id !== $user->id;
    }
}
