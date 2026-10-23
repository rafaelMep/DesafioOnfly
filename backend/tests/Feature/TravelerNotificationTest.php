<?php

namespace Tests\Feature;

use App\Models\TravelOrder;
use App\Models\User;
use App\Notifications\TravelOrderDecision;
use App\Notifications\TravelOrderRequested;
use App\Notifications\TravelOrderStatusChanged;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TravelerNotificationTest extends TestCase
{
    // Real commits exercise the after-commit email job, without contacting SMTP.
    use DatabaseMigrations;

    public static function decisions(): array
    {
        return [['aprovado', 'Pedido aprovado'], ['cancelado', 'Pedido cancelado']];
    }

    #[DataProvider('decisions')]
    public function test_decision_persists_an_inbox_notice_and_queues_the_original_email(string $status, string $title): void
    {
        Queue::fake([SendQueuedNotifications::class]);
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create();
        $order = TravelOrder::factory()->for($owner)->create();

        $this->actingAs($admin, 'api')->patchJson("/api/travel-orders/{$order->id}/status", compact('status'))->assertOk();
        $notice = $owner->notifications()->sole();
        $this->assertSame(TravelOrderDecision::class, $notice->type);
        $this->assertSame($status, $notice->data['status']);
        $this->assertSame($order->id, $notice->data['travel_order_id']);
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->actingAs($owner, 'api')->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('unread_count', 1)->assertJsonPath('data.0.kind', 'travel_order_status_changed')
            ->assertJsonPath('data.0.status', $status)->assertJsonPath('data.0.title', $title);
        Queue::assertPushed(SendQueuedNotifications::class, fn ($job) => $job->notification instanceof TravelOrderStatusChanged
            && $job->notifiables->first()->id === $owner->id && $job->afterCommit === true);
        Queue::assertPushed(SendQueuedNotifications::class, 1);
    }

    public function test_reading_and_marking_all_are_scoped_to_the_traveler(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $firstOrder = TravelOrder::factory()->for($first)->create(['status' => 'aprovado']);
        $secondOrder = TravelOrder::factory()->for($second)->create(['status' => 'cancelado']);
        $first->notify(new TravelOrderDecision($firstOrder));
        $second->notify(new TravelOrderDecision($secondOrder));
        $own = $first->notifications()->sole();
        $foreign = $second->notifications()->sole();
        $this->actingAs($first, 'api')->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.travel_order_id', $firstOrder->id);
        $this->patchJson('/api/notifications/'.$foreign->id.'/read')->assertNotFound();
        $this->getJson('/api/travel-orders/'.$secondOrder->id)->assertForbidden();
        $this->patchJson('/api/notifications/'.$own->id.'/read')->assertOk()->assertJsonPath('unread_count', 0);
        $this->patchJson('/api/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_admin_also_receives_decisions_about_their_own_orders(): void
    {
        Queue::fake([SendQueuedNotifications::class]);
        $owner = User::factory()->create(['is_admin' => true]);
        $actor = User::factory()->create(['is_admin' => true]);
        $order = TravelOrder::factory()->for($owner)->create();
        $this->actingAs($actor, 'api')->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'aprovado'])->assertOk();
        $this->actingAs($owner, 'api')->getJson('/api/notifications')->assertOk()
            ->assertJsonPath('data.0.kind', 'travel_order_status_changed')->assertJsonPath('data.0.status', 'aprovado');
    }

    public function test_invalid_or_repeated_decisions_do_not_duplicate_notices_or_emails(): void
    {
        Queue::fake([SendQueuedNotifications::class]);
        $order = TravelOrder::factory()->create();
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'api');
        $this->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'aprovado'])->assertOk();
        $this->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'cancelado'])->assertConflict();
        $this->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'aprovado'])->assertConflict();
        $this->assertDatabaseCount('notifications', 1);
        Queue::assertPushed(SendQueuedNotifications::class, 1);
    }

    public function test_inbox_failure_rolls_back_the_decision_and_never_queues_email(): void
    {
        Queue::fake([SendQueuedNotifications::class]);
        $order = TravelOrder::factory()->create();
        Event::listen('eloquent.creating: '.DatabaseNotification::class, function () {
            throw new \RuntimeException('Simulated inbox failure');
        });
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'api')
            ->patchJson("/api/travel-orders/{$order->id}/status", ['status' => 'cancelado'])->assertStatus(500);
        $this->assertSame('solicitado', $order->fresh()->status->value);
        $this->assertDatabaseCount('notifications', 0);
        Queue::assertNothingPushed();
    }

    public function test_former_admin_cannot_read_admin_request_notices_but_keeps_personal_decisions(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        $foreignOrder = TravelOrder::factory()->create();
        $user->notify(new TravelOrderRequested($foreignOrder));
        $adminNotice = $user->notifications()->sole();
        $ownOrder = TravelOrder::factory()->for($user)->create(['status' => 'aprovado']);
        $user->notify(new TravelOrderDecision($ownOrder));
        $user->forceFill(['is_admin' => false])->save();
        $this->actingAs($user, 'api')->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.travel_order_id', $ownOrder->id);
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('unread_count', 1);
        $this->patchJson('/api/notifications/'.$adminNotice->id.'/read')->assertNotFound();
        $this->patchJson('/api/notifications/read-all')->assertOk();
        $this->assertNull($adminNotice->fresh()->read_at);
    }
}
