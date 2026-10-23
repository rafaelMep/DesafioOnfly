<?php

namespace Tests\Feature;

use App\Models\TravelOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_respects_ownership_and_admin_visibility(): void
    {
        $user = User::factory()->create();
        TravelOrder::factory()->for($user)->create();
        TravelOrder::factory()->for($user)->create(['status' => 'aprovado']);
        TravelOrder::factory()->create(['status' => 'cancelado']);
        $this->actingAs($user, 'api')->getJson('/api/travel-orders/summary')
            ->assertOk()->assertExactJson(['data' => ['total' => 2, 'solicitado' => 1, 'aprovado' => 1, 'cancelado' => 0]]);
        $this->actingAs(User::factory()->create(['is_admin' => true]), 'api')->getJson('/api/travel-orders/summary')
            ->assertOk()->assertJsonPath('data.total', 3);
    }

    public function test_end_date_can_be_used_without_start_date(): void
    {
        $this->actingAs(User::factory()->create(), 'api')->getJson('/api/travel-orders?date_to=2027-12-31')->assertOk();
    }
}
