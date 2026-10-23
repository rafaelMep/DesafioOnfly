<?php

namespace App\Notifications;

use App\Enums\TravelStatus;
use App\Models\TravelOrder;
use Illuminate\Notifications\Notification;

class TravelOrderDecision extends Notification
{
    public function __construct(private TravelOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'travel_order_status_changed',
            'status' => $this->order->status->value,
            'travel_order_id' => $this->order->id,
            'title' => $this->order->status === TravelStatus::Approved ? 'Pedido aprovado' : 'Pedido cancelado',
            'requester_name' => $this->order->user->name,
            'destination' => $this->order->destination,
        ];
    }
}
