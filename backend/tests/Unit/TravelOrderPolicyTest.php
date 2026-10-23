<?php

namespace Tests\Unit;

use App\Models\TravelOrder;
use App\Models\User;
use App\Policies\TravelOrderPolicy;
use PHPUnit\Framework\TestCase;

class TravelOrderPolicyTest extends TestCase
{
    public function test_regular_user_can_view_only_their_own_order(): void
    {
        $user = new User;
        $user->id = 1;
        $order = new TravelOrder;
        $order->user_id = 1;
        $policy = new TravelOrderPolicy;

        $this->assertTrue($policy->view($user, $order));
        $order->user_id = 2;
        $this->assertFalse($policy->view($user, $order));
    }

    public function test_regular_user_cannot_change_any_order_status(): void
    {
        $user = new User;
        $user->id = 1;
        $order = new TravelOrder;
        $order->user_id = 1;
        $policy = new TravelOrderPolicy;

        $this->assertFalse($policy->updateStatus($user, $order));
        $order->user_id = 2;
        $this->assertFalse($policy->updateStatus($user, $order));
    }

    public function test_admin_can_manage_another_users_order_but_not_their_own(): void
    {
        $admin = new User;
        $admin->id = 1;
        $admin->is_admin = true;
        $order = new TravelOrder;
        $order->user_id = 2;
        $policy = new TravelOrderPolicy;

        $this->assertTrue($policy->view($admin, $order));
        $this->assertTrue($policy->updateStatus($admin, $order));
        $order->user_id = 1;
        $this->assertTrue($policy->view($admin, $order));
        $this->assertFalse($policy->updateStatus($admin, $order));
    }
}
