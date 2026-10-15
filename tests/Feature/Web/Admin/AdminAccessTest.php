<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Authorization matrix for the read-only SUPERADMIN console: guests are sent to
 * login, buyers are forbidden (AdminPolicy denies), superadmins get the pages.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    public static function adminRoutes(): array
    {
        return [
            '/admin/dasbor',
            '/admin/rfq',
            '/admin/pesanan',
            '/admin/tagihan',
            '/admin/pembayaran',
            '/admin/audit',
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        foreach (self::adminRoutes() as $route) {
            $this->get($route)->assertRedirect(route('login'));
        }
    }

    public function test_buyer_is_forbidden_from_every_admin_route(): void
    {
        $buyer = User::factory()->buyerB2b()->create();

        foreach (self::adminRoutes() as $route) {
            $this->actingAs($buyer)->get($route)->assertForbidden();
        }
    }

    public function test_superadmin_can_reach_every_admin_route(): void
    {
        $admin = User::factory()->superadmin()->create();

        foreach (self::adminRoutes() as $route) {
            $this->actingAs($admin)->get($route)->assertOk();
        }
    }
}
