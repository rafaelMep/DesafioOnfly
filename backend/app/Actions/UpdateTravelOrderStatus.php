<?php

namespace App\Actions;

use App\Enums\TravelStatus;
use App\Models\TravelOrder;
use App\Models\User;
use App\Notifications\TravelOrderDecision;
use App\Notifications\TravelOrderStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class UpdateTravelOrderStatus
{
    public function execute(User $actor, TravelOrder $order, TravelStatus $status): TravelOrder
    {
        return DB::transaction(function () use ($actor, $order, $status) {
            // Serialize concurrent approvals/cancellations on the same order.
            $locked = TravelOrder::query()->lockForUpdate()->findOrFail($order->id);
            Gate::forUser($actor)->authorize('updateStatus', $locked);

            if (! $locked->status->canTransitionTo($status)) {
                throw new ConflictHttpException('Somente pedidos solicitados podem ser aprovados ou cancelados.');
            }

            $locked->status = $status;
            $locked->save();
            // The recipient's inbox and the decision commit atomically, independently of email delivery.
            $locked->user->notify(new TravelOrderDecision($locked));
            $locked->user->notify((new TravelOrderStatusChanged($locked))->afterCommit());

            return $locked;
        });
    }
}
