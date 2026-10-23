<?php

namespace Tests\Feature;

use App\Enums\TravelStatus;
use App\Models\TravelOrder;
use App\Models\User;
use App\Notifications\TravelOrderStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TravelOrderTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'destination' => 'Belo Horizonte, MG',
            'departure_date' => now()->addDays(7)->toDateString(),
            'return_date' => now()->addDays(10)->toDateString(),
        ], $overrides);
    }

    public function test_api_requires_authentication(): void
    {
        $order = TravelOrder::factory()->create();
        $this->getJson('/api/travel-orders')->assertUnauthorized();
        $this->postJson('/api/travel-orders', $this->payload())->assertUnauthorized();
        $this->getJson("/api/travel-orders/{$order->id}")->assertUnauthorized();
        $this->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'aprovado'])->assertUnauthorized();
    }

    public function test_creation_associates_order_with_authenticated_user(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user, 'api')->postJson('/api/travel-orders', $this->payload());

        $response->assertCreated()->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.requester_name', $user->name)
            ->assertJsonPath('data.status', 'solicitado');
        $this->assertDatabaseCount('travel_orders', 1);
    }

    public function test_cannot_supply_another_owner_or_initial_status(): void
    {
        $this->actingAs(User::factory()->create(), 'api')
            ->postJson('/api/travel-orders', $this->payload(['user_id' => 999, 'status' => 'aprovado']))
            ->assertUnprocessable()->assertJsonValidationErrors(['user_id', 'status']);
        $this->assertDatabaseCount('travel_orders', 0);
    }

    public function test_invalid_dates_and_destination_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'api')
            ->postJson('/api/travel-orders', $this->payload([
                'destination' => '',
                'departure_date' => now()->subDays(2)->toDateString(),
                'return_date' => now()->subDays(3)->toDateString(),
            ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['destination', 'departure_date', 'return_date']);
    }

    public function test_user_lists_only_own_orders_and_cannot_read_another_users_order(): void
    {
        $user = User::factory()->create();
        $own = TravelOrder::factory()->for($user)->create();
        $other = TravelOrder::factory()->create();

        $this->actingAs($user, 'api')->getJson('/api/travel-orders')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson("/api/travel-orders/{$own->id}")->assertOk();
        $this->getJson("/api/travel-orders/{$other->id}")->assertForbidden();
        $this->getJson('/api/travel-orders/99999')->assertNotFound();
    }

    public function test_admin_can_list_and_read_orders_from_other_users(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $orders = TravelOrder::factory()->count(2)->create();

        $this->actingAs($admin, 'api')->getJson('/api/travel-orders')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/travel-orders/'.$orders->first()->id)->assertOk();
    }

    public function test_admin_can_approve_and_notify_requester(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $order = TravelOrder::factory()->create();

        $this->actingAs($admin, 'api')->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'aprovado'])
            ->assertOk()->assertJsonPath('data.status', 'aprovado');
        $this->assertDatabaseHas('travel_orders', ['id' => $order->id, 'status' => 'aprovado']);
        Notification::assertSentTo($order->user, TravelOrderStatusChanged::class);
    }

    public function test_admin_can_cancel_requested_order_and_notify_requester(): void
    {
        Notification::fake();
        $order = TravelOrder::factory()->create();

        $this->actingAs(User::factory()->create(['is_admin' => true]), 'api')
            ->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'cancelado'])
            ->assertOk()->assertJsonPath('data.status', 'cancelado');
        Notification::assertSentTo($order->user, TravelOrderStatusChanged::class);
    }

    public function test_approved_order_cannot_be_cancelled_or_approved_again(): void
    {
        Notification::fake();
        $order = TravelOrder::factory()->create(['status' => TravelStatus::Approved]);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'api');

        foreach (['cancelado', 'aprovado'] as $status) {
            $this->patchJson("/api/travel-orders/{$order->id}/status", compact('status'))->assertConflict();
        }

        $this->assertSame(TravelStatus::Approved, $order->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_cancelled_order_cannot_be_reopened(): void
    {
        Notification::fake();
        $order = TravelOrder::factory()->create(['status' => TravelStatus::Cancelled]);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'api')
            ->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'aprovado'])->assertConflict();
        Notification::assertNothingSent();
    }

    public function test_requester_cannot_update_status_even_if_admin(): void
    {
        Notification::fake();
        foreach ([false, true] as $isAdmin) {
            $owner = User::factory()->create(['is_admin' => $isAdmin]);
            $order = TravelOrder::factory()->for($owner)->create();
            $this->actingAs($owner, 'api')->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'aprovado'])
                ->assertForbidden();
        }
        Notification::assertNothingSent();
    }

    public function test_regular_user_cannot_change_another_users_order(): void
    {
        $order = TravelOrder::factory()->create();
        $this->actingAs(User::factory()->create(), 'api')
            ->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'cancelado'])->assertForbidden();
    }

    public function test_status_update_rejects_invalid_or_requested_status(): void
    {
        $order = TravelOrder::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'api')
            ->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'solicitado'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_filters_are_combined_without_leaking_other_users_orders(): void
    {
        $user = User::factory()->create();
        $match = TravelOrder::factory()->for($user)->create(['destination' => 'Belo Horizonte', 'status' => 'aprovado']);
        TravelOrder::factory()->for($user)->create(['destination' => 'Recife', 'status' => 'aprovado']);
        TravelOrder::factory()->for($user)->create(['destination' => 'Belo Horizonte', 'status' => 'cancelado']);
        TravelOrder::factory()->create(['destination' => 'Belo Horizonte', 'status' => 'aprovado']);

        $this->actingAs($user, 'api')->getJson('/api/travel-orders?status=aprovado&destination=BELO')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public function test_travel_date_filter_includes_overlapping_trips(): void
    {
        $user = User::factory()->create();
        $match = TravelOrder::factory()->for($user)->create([
            'departure_date' => '2027-03-01', 'return_date' => '2027-03-15',
        ]);
        TravelOrder::factory()->for($user)->create(['departure_date' => '2027-04-01', 'return_date' => '2027-04-05']);

        $this->actingAs($user, 'api')->getJson('/api/travel-orders?date_type=travel&date_from=2027-03-05&date_to=2027-03-10')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public function test_creation_period_filter_is_inclusive(): void
    {
        $user = User::factory()->create();
        $match = TravelOrder::factory()->for($user)->create(['created_at' => '2026-01-15 23:59:59']);
        TravelOrder::factory()->for($user)->create(['created_at' => '2026-01-16 00:00:00']);

        $this->actingAs($user, 'api')->getJson('/api/travel-orders?date_type=created&date_from=2026-01-15&date_to=2026-01-15')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'api')
            ->getJson('/api/travel-orders?status=invalid&per_page=1000&date_from=2027-03-10&date_to=2027-03-01')
            ->assertUnprocessable()->assertJsonValidationErrors(['status', 'per_page', 'date_to']);
    }

    public function test_listing_is_paginated(): void
    {
        $user = User::factory()->create();
        TravelOrder::factory()->for($user)->count(12)->create();
        $this->actingAs($user, 'api')->getJson('/api/travel-orders')
            ->assertOk()->assertJsonCount(10, 'data')->assertJsonPath('meta.total', 12);
        $this->getJson('/api/travel-orders?page=2')->assertOk()->assertJsonCount(2, 'data');
    }
}
