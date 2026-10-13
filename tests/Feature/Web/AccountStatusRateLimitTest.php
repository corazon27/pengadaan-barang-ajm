<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Account-access surface — signed status page throttling.
 *
 * `GET /status-akun/{user}` sits behind the `signed` middleware and the
 * `account-status` limiter (5 requests/minute keyed by client IP for this
 * endpoint, which carries no email input). The limiter is an initial
 * engineering setting, not a measured traffic limit; the key uses the resolved
 * client IP ($request->ip()) and no forwarded header is trusted.
 */
class AccountStatusRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const IP_A = '203.0.113.30';

    private const IP_B = '198.51.100.40';

    private function asIp(string $ip): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip]);
    }

    private function signedStatusUrl(User $user): string
    {
        return URL::temporarySignedRoute('account.status', now()->addHour(), [
            'user' => $user->getKey(),
        ]);
    }

    public function test_status_requests_below_limit_succeed(): void
    {
        $user = User::factory()->pending()->create();
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 5; $i++) {
            $this->get($this->signedStatusUrl($user))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/AccountStatus'));
        }
    }

    public function test_sixth_status_request_is_throttled_with_429(): void
    {
        $user = User::factory()->pending()->create();
        $url = $this->signedStatusUrl($user);
        $this->asIp(self::IP_A);

        for ($i = 0; $i < 5; $i++) {
            $this->get($url)->assertOk();
        }

        $response = $this->get($url);

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $response->assertHeader('X-RateLimit-Limit', '5');
        $response->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_separate_ips_have_independent_status_buckets(): void
    {
        $user = User::factory()->pending()->create();
        $url = $this->signedStatusUrl($user);

        $this->asIp(self::IP_A);
        for ($i = 0; $i < 5; $i++) {
            $this->get($url)->assertOk();
        }
        $this->get($url)->assertStatus(429);

        $this->asIp(self::IP_B);
        $this->get($url)->assertOk();
    }

    public function test_unsigned_status_request_is_forbidden(): void
    {
        $user = User::factory()->pending()->create();
        $this->asIp(self::IP_A);

        $this->get(route('account.status', ['user' => $user->getKey()]))->assertForbidden();
    }
}
