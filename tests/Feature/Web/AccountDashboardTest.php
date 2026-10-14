<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\OrderStatus;
use App\Enums\RfqStatus;
use App\Models\Order;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/akun')->assertRedirect(route('login'));
    }

    public function test_inactive_account_is_redirected_to_login(): void
    {
        $user = User::factory()->pending()->create();

        $this->actingAs($user)->get('/akun')->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_profile_summary_and_recent_orders_scoped_to_owner(): void
    {
        $user = User::factory()->buyerB2b()->create(['full_name' => 'Budi Santoso']);
        $other = User::factory()->buyerB2g()->create();

        Order::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(3)->create(['user_id' => $other->id]);
        Rfq::factory()->create(['user_id' => $user->id, 'status' => RfqStatus::SUBMITTED]);
        Rfq::factory()->count(4)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get('/akun')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Home')
                ->where('account.full_name', 'Budi Santoso')
                ->where('account.role', 'BUYER_B2B')
                ->where('summary.orders_active', 2)
                ->where('summary.rfqs_open', 1)
                ->where('summary.invoices_unpaid', 0)
                ->where('summary.notifications_unread', 0)
                ->has('recentOrders', 2));
    }

    public function test_completed_and_cancelled_orders_are_not_counted_as_active(): void
    {
        $user = User::factory()->buyerB2b()->create();

        Order::factory()->create(['user_id' => $user->id, 'status' => 'COMPLETED']);
        Order::factory()->create(['user_id' => $user->id, 'status' => 'CANCELLED']);
        Order::factory()->create(['user_id' => $user->id, 'status' => 'PROCESSING']);

        $this->actingAs($user)
            ->get('/akun')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.orders_active', 1)
                ->has('recentOrders', 3));
    }

    public function test_quoted_and_approved_rfqs_stay_open_and_delivered_orders_stay_active(): void
    {
        $user = User::factory()->buyerB2b()->create();

        Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::DELIVERED->value]);
        Order::factory()->create(['user_id' => $user->id, 'status' => OrderStatus::COMPLETED->value]);

        Rfq::factory()->create(['user_id' => $user->id, 'status' => RfqStatus::QUOTED]);
        Rfq::factory()->create(['user_id' => $user->id, 'status' => RfqStatus::APPROVED]);
        Rfq::factory()->create(['user_id' => $user->id, 'status' => RfqStatus::CONVERTED_TO_ORDER]);

        $this->actingAs($user)
            ->get('/akun')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.orders_active', 1)
                ->where('summary.rfqs_open', 2));
    }
}
