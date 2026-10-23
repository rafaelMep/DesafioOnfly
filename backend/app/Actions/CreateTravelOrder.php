<?php

namespace App\Actions;

use App\Models\TravelOrder;
use App\Models\User;
use App\Notifications\TravelOrderRequested;
use Illuminate\Support\Facades\DB;

class CreateTravelOrder
{
    public function execute(User $requester, array $attributes): TravelOrder
    {
        return DB::transaction(function () use ($requester, $attributes) {
            $order = $requester->travelOrders()->create($attributes)->refresh()->load('user');

            // Database-only notifications share the order transaction: either both persist or neither.
            // Exclude the creator, who cannot manage their own order even when they are an admin.
            User::query()->where('is_admin', true)->where('id', '!=', $requester->id)
                ->chunkById(100, function ($admins) use ($order) {
                    foreach ($admins as $admin) {
                        $admin->notify(new TravelOrderRequested($order));
                    }
                });

            return $order;
        });
    }
}
