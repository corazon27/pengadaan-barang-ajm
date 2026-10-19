<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `auth-write` limiter on the buyer web RFQ submission: 20 requests/minute keyed
 * by authenticated user + client IP. An initial engineering setting, not a
 * measured traffic limit.
 */
class AccountRfqCreateRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const IP_A = '203.0.113.70';

    private const IP_B = '198.51.100.80';

    private function asIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function payload(Product $product): array
    {
        return ['items' => [['product_id' => $product->id, 'quantity' => 1]]];
    }

    public function test_twenty_first_web_submission_is_throttled_with_429(): void
    {
        Notification::fake();

        $user = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->post('/akun/rfq', $this->payload($product))->assertRedirect();
        }

        $this->actingAs($user)
            ->post('/akun/rfq', $this->payload($product))
            ->assertStatus(429);

        $this->assertSame(20, Rfq::query()->count());
    }

    public function test_web_submission_buckets_are_independent_per_user(): void
    {
        Notification::fake();

        $userA = User::factory()->buyerB2b()->create();
        $userB = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();

        $this->asIp(self::IP_A);
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($userA)->post('/akun/rfq', $this->payload($product))->assertRedirect();
        }
        $this->actingAs($userA)->post('/akun/rfq', $this->payload($product))->assertStatus(429);

        $this->actingAs($userB)->post('/akun/rfq', $this->payload($product))->assertRedirect();
    }
}
