<?php

namespace App\Notifications;

use App\Models\TravelOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TravelOrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public TravelOrder $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Pedido #{$this->order->id} — {$this->order->status->value}")
            ->greeting("Olá, {$notifiable->name}!")
            ->line("Seu pedido de viagem para {$this->order->destination} foi {$this->order->status->value}.")
            ->line('Ida: '.$this->order->departure_date->format('d/m/Y'))
            ->line('Volta: '.$this->order->return_date->format('d/m/Y'))
            ->action('Ver pedidos', config('app.frontend_url').'/');
    }
}
