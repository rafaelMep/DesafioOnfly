<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Old request notices remain readable without rewriting their stored payloads.
            'kind' => $this->data['kind'] ?? 'travel_order_requested',
            'status' => $this->data['status'] ?? null,
            'travel_order_id' => (int) $this->data['travel_order_id'],
            'title' => $this->data['title'],
            'requester_name' => $this->data['requester_name'],
            'destination' => $this->data['destination'],
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
