<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Authorization;

use App\Models\Order;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScopeLeakTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_rfq_listing_scoped_to_own(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        Rfq::factory()->count(3)->create(['user_id' => $user->id]);
        Rfq::factory()->count(5)->create(['user_id' => $other->id]);

        $this->actingAs($user);

        $this->getJson('/api/v1/rfqs')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_non_admin_order_listing_scoped_to_own(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        Order::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(7)->create(['user_id' => $other->id]);

        $this->actingAs($user);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
