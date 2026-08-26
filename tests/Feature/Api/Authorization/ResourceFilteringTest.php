<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Authorization;

use App\Models\Order;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_own_npwp_number_in_profile(): void
    {
        $user = User::factory()->buyerB2b()->create([
            'npwp_number' => '12.345.678.9-012.345',
        ]);

        $this->actingAs($user);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.npwp_number', '12.345.678.9-012.345');
    }

    public function test_superadmin_sees_npwp_number_in_profile(): void
    {
        $admin = User::factory()->superadmin()->create([
            'npwp_number' => '99.888.777.6-555.444',
        ]);

        $this->actingAs($admin);

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.npwp_number', '99.888.777.6-555.444');
    }

    public function test_policy_blocks_cross_user_rfq_access(): void
    {
        $owner = User::factory()->buyerB2b()->create([
            'npwp_number' => '11.222.333.4-555.666',
        ]);

        $viewer = User::factory()->buyerB2b()->create();

        $rfq = Rfq::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($viewer);

        $this->getJson('/api/v1/rfqs/'.$rfq->id)
            ->assertForbidden();
    }

    public function test_policy_blocks_cross_user_order_access(): void
    {
        $owner = User::factory()->buyerB2b()->create([
            'npwp_number' => '77.666.555.4-333.222',
        ]);

        $viewer = User::factory()->buyerB2b()->create();

        $order = Order::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($viewer);

        $this->getJson('/api/v1/orders/'.$order->id)
            ->assertForbidden();
    }
}
