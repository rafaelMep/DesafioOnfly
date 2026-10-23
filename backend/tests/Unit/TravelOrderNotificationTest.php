<?php

namespace Tests\Unit;

use App\Models\TravelOrder;
use App\Models\User;
use App\Notifications\TravelOrderStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

class TravelOrderNotificationTest extends TestCase
{
    public function test_notification_is_queued_after_commit_and_contains_trip_details(): void
    {
        $order = new TravelOrder(['destination' => 'Recife', 'departure_date' => '2027-01-10', 'return_date' => '2027-01-15']);
        $order->id = 42;
        $order->status = 'aprovado';
        $notification = (new TravelOrderStatusChanged($order))->afterCommit();
        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
        $this->assertSame(['mail'], $notification->via(new User));
        $mail = $notification->toMail(new User(['name' => 'Rafael']));
        $this->assertStringContainsString('42', $mail->subject);
        $this->assertStringContainsString('Recife', implode(' ', $mail->introLines));
        $this->assertStringContainsString('10/01/2027', implode(' ', $mail->introLines));
    }
}
