<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public Surface Rate-Limiting Follow-up — public web surface.
 *
 * `GET /katalog` and `GET /produk/{sku}` are throttled at 60 requests/minute per
 * client IP through the `public-web` limiter. `/` and `/cara-order` are
 * intentionally excluded from any endpoint-specific limiter. The limiter key is
 * the resolved client IP ($request->ip()); no forwarded header is trusted.
 */
class PublicRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const IP_A = '203.0.113.10';

    private const IP_B = '198.51.100.20';

    private function asIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_katalog_requests_below_limit_succeed(): void
    {
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 60; $i++) {
            $this->get('/katalog')->assertOk();
        }
    }

    public function test_produk_detail_requests_below_limit_succeed(): void
    {
        Product::factory()->create(['sku' => 'SKU-RL-001']);
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 60; $i++) {
            $this->get('/produk/SKU-RL-001')->assertOk();
        }
    }

    public function test_sixty_first_web_request_is_throttled_with_429(): void
    {
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 60; $i++) {
            $this->get('/katalog')->assertOk();
        }

        $response = $this->get('/katalog');

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $response->assertHeader('X-RateLimit-Limit', '60');
        $response->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_separate_ips_have_independent_web_buckets(): void
    {
        $this->asIp(self::IP_A);
        for ($i = 0; $i < 60; $i++) {
            $this->get('/katalog')->assertOk();
        }
        $this->get('/katalog')->assertStatus(429);

        $this->asIp(self::IP_B);
        $this->get('/katalog')->assertOk();
    }

    public function test_web_and_api_buckets_are_independent(): void
    {
        Product::factory()->create();
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 60; $i++) {
            $this->get('/katalog')->assertOk();
        }
        $this->get('/katalog')->assertStatus(429);

        // The same IP still has its full API bucket available.
        $this->getJson('/api/v1/products')->assertOk();
    }

    public function test_home_and_cara_order_are_not_endpoint_limited(): void
    {
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 61; $i++) {
            $this->get('/')->assertOk();
            $this->get('/cara-order')->assertOk();
        }
    }
}
