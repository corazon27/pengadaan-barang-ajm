<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `auth-write` limiter on the existing Sanctum RFQ submission endpoint
 * (`POST /api/v1/rfqs`): 20 requests/minute keyed by authenticated user + IP.
 * The API contract and authorization are unchanged; only throttling is added.
 */
class RfqRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const IP_A = '203.0.113.90';

    private const IP_B = '198.51.100.100';

    private function asIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function payload(Product $product): array
    {
        return ['items' => [['product_id' => $product->id, 'quantity' => 1]]];
    }

    public function test_twenty_first_api_submission_is_throttled_with_429(): void
    {
        Notification::fake();

        $user = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)
                ->postJson('/api/v1/rfqs', $this->payload($product))
                ->assertCreated();
        }

        $this->actingAs($user)
            ->postJson('/api/v1/rfqs', $this->payload($product))
            ->assertStatus(429);

        $this->assertSame(20, Rfq::query()->count());
    }

    public function test_api_submission_buckets_are_independent_per_user(): void
    {
        Notification::fake();

        $userA = User::factory()->buyerB2b()->create();
        $userB = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();

        $this->asIp(self::IP_A);
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($userA)
                ->postJson('/api/v1/rfqs', $this->payload($product))
                ->assertCreated();
        }
        $this->actingAs($userA)
            ->postJson('/api/v1/rfqs', $this->payload($product))
            ->assertStatus(429);

        $this->actingAs($userB)
            ->postJson('/api/v1/rfqs', $this->payload($product))
            ->assertCreated();
    }
}
