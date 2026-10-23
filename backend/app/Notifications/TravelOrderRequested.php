<?php

namespace App\Notifications;

use App\Models\TravelOrder;
use Illuminate\Notifications\Notification;

class TravelOrderRequested extends Notification
{
    public function __construct(private TravelOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'travel_order_requested',
            'status' => null,
            'travel_order_id' => $this->order->id,
            'title' => 'Novo pedido de viagem',
            'requester_name' => $this->order->user->name,
            'destination' => $this->order->destination,
        ];
    }
}
