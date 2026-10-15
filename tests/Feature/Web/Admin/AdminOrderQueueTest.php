<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminOrderQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_orders_from_all_buyers(): void
    {
        $admin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        Order::factory()->count(2)->create(['user_id' => $buyer->id]);
        Order::factory()->count(3)->create(['user_id' => $other->id]);

        $this->actingAs($admin)
            ->get('/admin/pesanan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index')
                ->where('pagination.total', 5)
                ->where('pagination.baseUrl', '/admin/pesanan'));
    }

    public function test_status_filter_narrows_the_queue(): void
    {
        $admin = User::factory()->superadmin()->create();

        Order::factory()->count(2)->create(['status' => OrderStatus::SHIPPED]);
        Order::factory()->count(4)->create(['status' => OrderStatus::PENDING_PAYMENT]);

        $this->actingAs($admin)
            ->get('/admin/pesanan?status=SHIPPED')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 2));
    }

    public function test_order_detail_never_exposes_private_document_urls(): void
    {
        $admin = User::factory()->superadmin()->create();
        $order = Order::factory()->create([
            'order_number' => 'ORD-ADMIN-1',
            'po_document_url' => 'https://private.example.com/po.pdf',
            'lkpp_product_url' => 'https://private.example.com/lkpp',
        ]);

        $this->actingAs($admin)
            ->get("/admin/pesanan/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Show')
                ->where('order.order_number', 'ORD-ADMIN-1')
                ->missing('order.po_document_url')
                ->missing('order.lkpp_product_url'));
    }

    public function test_buyer_is_forbidden_from_admin_order_surfaces(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create();

        $this->actingAs($buyer)->get('/admin/pesanan')->assertForbidden();
        $this->actingAs($buyer)->get("/admin/pesanan/{$order->id}")->assertForbidden();
    }
}
