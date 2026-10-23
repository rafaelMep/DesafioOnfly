<?php

namespace Tests\Feature;

use App\Models\TravelOrder;
use App\Models\User;
use App\Notifications\TravelOrderRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['destination' => 'Ouro Preto, MG, Brasil', 'departure_date' => '2099-01-10', 'return_date' => '2099-01-12'];
    }

    public function test_new_order_persists_notifications_for_offline_admins_only(): void
    {
        $admins = User::factory()->count(2)->create(['is_admin' => true]);
        $requester = User::factory()->create();
        $otherTraveler = User::factory()->create();
        $response = $this->actingAs($requester, 'api')->postJson('/api/travel-orders', $this->payload())->assertCreated();
        foreach ($admins as $admin) {
            $item = $admin->notifications()->sole();
            $this->assertSame($response->json('data.id'), $item->data['travel_order_id']);
            $this->assertNull($item->read_at);
        }
        $this->assertSame(0, $requester->notifications()->count());
        $this->assertSame(0, $otherTraveler->notifications()->count());
        $this->actingAs($admins->first(), 'api')->getJson('/api/notifications')
            ->assertOk()->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.destination', 'Ouro Preto, MG, Brasil');
    }

    public function test_admin_requester_does_not_receive_an_actionable_self_notification(): void
    {
        $requester = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => true]);
        $this->actingAs($requester, 'api')->postJson('/api/travel-orders', $this->payload())->assertCreated();
        $this->assertSame(0, $requester->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
    }

    public function test_failed_notification_rolls_back_the_order_transaction(): void
    {
        User::factory()->create(['is_admin' => true]);
        Event::listen('eloquent.creating: '.DatabaseNotification::class, function () {
            throw new \RuntimeException('Simulated database notification failure');
        });
        $this->actingAs(User::factory()->create(), 'api')->postJson('/api/travel-orders', $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('travel_orders', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_notification_endpoints_require_authentication_and_traveler_inbox_is_empty(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/notifications/unread-count')->assertUnauthorized();
        $this->patchJson('/api/notifications/read-all')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'api');
        $this->getJson('/api/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('unread_count', 0);
        $this->patchJson('/api/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->patchJson('/api/notifications/00000000-0000-4000-8000-000000000000/read')->assertNotFound();
    }

    public function test_each_admin_can_only_access_their_own_inbox_and_mark_read_idempotently(): void
    {
        $first = User::factory()->create(['is_admin' => true]);
        $second = User::factory()->create(['is_admin' => true]);
        $order = TravelOrder::factory()->create();
        $first->notify(new TravelOrderRequested($order));
        $second->notify(new TravelOrderRequested($order));
        $own = $first->notifications()->sole();
        $foreign = $second->notifications()->sole();
        $this->actingAs($first, 'api')->getJson('/api/notifications')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->patchJson('/api/notifications/'.$foreign->id.'/read')->assertNotFound();
        $this->assertNull($foreign->fresh()->read_at);
        $response = $this->patchJson('/api/notifications/'.$own->id.'/read')->assertOk()->assertJsonPath('unread_count', 0);
        $readAt = $response->json('data.read_at');
        $this->travel(1)->hours();
        $this->patchJson('/api/notifications/'.$own->id.'/read')->assertOk()->assertJsonPath('data.read_at', $readAt);
        $this->getJson('/api/notifications/unread-count')->assertExactJson(['unread_count' => 0]);
    }

    public function test_mark_all_read_does_not_touch_other_admins(): void
    {
        $first = User::factory()->create(['is_admin' => true]);
        $second = User::factory()->create(['is_admin' => true]);
        $order = TravelOrder::factory()->create();
        $first->notify(new TravelOrderRequested($order));
        $second->notify(new TravelOrderRequested($order));
        $this->actingAs($first, 'api')->patchJson('/api/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertSame(1, $second->unreadNotifications()->count());
    }

    public function test_inbox_is_paginated_and_can_filter_unread_items(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $order = TravelOrder::factory()->create();
        for ($i = 0; $i < 25; $i++) {
            $admin->notify(new TravelOrderRequested($order));
        }
        $admin->notifications()->first()->markAsRead();
        $this->actingAs($admin, 'api')->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 25)->assertJsonPath('unread_count', 24);
        $this->getJson('/api/notifications?page=2')->assertOk()->assertJsonCount(5, 'data');
        $this->getJson('/api/notifications?unread_only=1')->assertOk()->assertJsonPath('meta.total', 24);
        $this->getJson('/api/notifications?unread_only=wrong')->assertUnprocessable();
    }
}
