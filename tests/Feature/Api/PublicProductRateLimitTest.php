<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public Surface Rate-Limiting Follow-up — public API surface.
 *
 * `GET /api/v1/products` and `GET /api/v1/products/{product}` are throttled at
 * 60 requests/minute per client IP through the `public-api` limiter, independently
 * of the public web bucket. Reads stay unauthenticated; the 429 response keeps the
 * standard API envelope and the framework throttle headers.
 */
class PublicProductRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const IP_A = '203.0.113.30';

    private const IP_B = '198.51.100.40';

    private function asIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    public function test_public_can_list_products_below_limit_without_auth(): void
    {
        Product::factory()->create();
        $this->asIp(self::IP_A);

        $this->getJson('/api/v1/products')->assertOk();
    }

    public function test_public_can_view_product_below_limit_without_auth(): void
    {
        $product = Product::factory()->create();
        $this->asIp(self::IP_A);

        $this->getJson('/api/v1/products/'.$product->id)->assertOk();
    }

    public function test_sixty_first_api_request_is_throttled_with_envelope(): void
    {
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/products')->assertOk();
        }

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['success', 'message', 'data', 'errors' => ['throttle']]);

        $response->assertHeader('Retry-After');
        $response->assertHeader('X-RateLimit-Limit', '60');
        $response->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_separate_ips_have_independent_api_buckets(): void
    {
        $this->asIp(self::IP_A);
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/products')->assertOk();
        }
        $this->getJson('/api/v1/products')->assertStatus(429);

        $this->asIp(self::IP_B);
        $this->getJson('/api/v1/products')->assertOk();
    }

    public function test_api_and_web_buckets_are_independent(): void
    {
        $this->asIp(self::IP_A);
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/products')->assertOk();
        }
        $this->getJson('/api/v1/products')->assertStatus(429);

        // The same IP still has its full web bucket available.
        $this->get('/katalog')->assertOk();
    }

    public function test_api_show_and_index_share_the_api_bucket(): void
    {
        $product = Product::factory()->create();
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/v1/products')->assertOk();
        }

        $this->getJson('/api/v1/products/'.$product->id)->assertStatus(429);
    }
}
