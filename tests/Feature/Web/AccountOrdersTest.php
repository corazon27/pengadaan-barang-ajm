<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/akun/pesanan')->assertRedirect(route('login'));
    }

    public function test_index_lists_only_own_orders(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        Order::factory()->count(2)->create(['user_id' => $user->id]);
        Order::factory()->count(4)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get('/akun/pesanan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Orders/Index')
                ->has('orders', 2)
                ->where('pagination.total', 2)
                ->where('pagination.baseUrl', '/akun/pesanan'));
    }

    public function test_owner_can_view_their_order_detail_with_items(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'order_number' => 'ORD-TEST-1']);
        $product = Product::factory()->create(['sku' => 'SKU-1', 'title' => 'Laptop']);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($user)
            ->get("/akun/pesanan/{$order->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Orders/Show')
                ->where('order.order_number', 'ORD-TEST-1')
                ->has('order.items', 1)
                ->where('order.items.0.product_sku', 'SKU-1'));
    }

    public function test_cross_buyer_order_detail_is_forbidden(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        $order = Order::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get("/akun/pesanan/{$order->id}")
            ->assertForbidden();
    }

    public function test_superadmin_index_sees_orders_from_all_buyers(): void
    {
        $admin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        Order::factory()->count(2)->create(['user_id' => $buyer->id]);
        Order::factory()->count(3)->create(['user_id' => $other->id]);

        $this->actingAs($admin)
            ->get('/akun/pesanan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Orders/Index')
                ->where('pagination.total', 5));
    }
}
