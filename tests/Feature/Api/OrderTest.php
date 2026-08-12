<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\BastStatus;
use App\Enums\OrderStatus;
use App\Enums\RfqStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_convert_approved_rfq_to_order(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create(['base_price' => 100000.00]);
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::APPROVED]);
        RfqItem::factory()->create([
            'rfq_id' => $rfq->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'negotiated_price' => 95000.00,
        ]);

        $this->actingAs($buyer);

        $response = $this->postJson('/api/v1/orders', [
            'rfq_id' => $rfq->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', OrderStatus::PENDING_PAYMENT->value)
            ->assertJsonPath('data.total_amount', '285000.00')
            ->assertJsonPath('data.items.0.unit_price', '95000.00')
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonStructure([
                'data' => [
                    'order_number',
                    'status',
                    'items' => [
                        '*' => [
                            'product_id',
                            'quantity',
                            'unit_price',
                            'subtotal',
                        ],
                    ],
                ],
            ]);

        $rfq->refresh();
        $this->assertEquals(RfqStatus::CONVERTED_TO_ORDER, $rfq->status);

        $this->assertDatabaseHas('orders', [
            'user_id' => $buyer->id,
            'rfq_id' => $rfq->id,
            'status' => OrderStatus::PENDING_PAYMENT->value,
        ]);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $response->json('data.id'),
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 95000.00,
            'subtotal' => 285000.00,
        ]);
    }

    public function test_buyer_cannot_convert_non_approved_rfq(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::SUBMITTED]);

        $this->actingAs($buyer);

        $response = $this->postJson('/api/v1/orders', [
            'rfq_id' => $rfq->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.rfq_id.0', 'RFQ harus berstatus APPROVED untuk dikonversi.');
    }

    public function test_non_owner_cannot_convert_rfq_to_order(): void
    {
        $owner = User::factory()->buyerB2b()->create();
        $otherBuyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $owner->id, 'status' => RfqStatus::APPROVED]);

        $this->actingAs($otherBuyer);

        $response = $this->postJson('/api/v1/orders', [
            'rfq_id' => $rfq->id,
        ]);

        $response->assertForbidden();
    }

    public function test_rfq_already_converted_returns_422(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::APPROVED]);
        Order::factory()->create(['user_id' => $buyer->id, 'rfq_id' => $rfq->id]);

        $this->actingAs($buyer);

        $response = $this->postJson('/api/v1/orders', [
            'rfq_id' => $rfq->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.rfq_id.0', 'RFQ ini sudah memiliki pesanan.');
    }

    public function test_buyer_can_only_view_own_orders(): void
    {
        $buyer1 = User::factory()->buyerB2b()->create();
        $buyer2 = User::factory()->buyerB2b()->create();

        $order1 = Order::factory()->create(['user_id' => $buyer1->id]);
        Order::factory()->create(['user_id' => $buyer2->id]);

        $this->actingAs($buyer1);

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $order1->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_superadmin_can_view_all_orders(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer1 = User::factory()->buyerB2b()->create();
        $buyer2 = User::factory()->buyerB2b()->create();

        Order::factory()->create(['user_id' => $buyer1->id]);
        Order::factory()->create(['user_id' => $buyer2->id]);

        $this->actingAs($superadmin);

        $response = $this->getJson('/api/v1/orders');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_superadmin_can_advance_order_to_delivered_and_generates_bast(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $this->actingAs($superadmin);

        foreach ([OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $status) {
            $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => $status->value,
            ]);

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.status', $status->value);
        }

        $this->assertDatabaseHas('bast_documents', [
            'order_id' => $order->id,
            'status' => BastStatus::PENDING_SIGNATURE->value,
        ]);
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $this->actingAs($superadmin);

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => OrderStatus::SHIPPED->value,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.status.0', 'Tidak dapat mengubah status dari PENDING_PAYMENT ke SHIPPED.');
    }

    public function test_buyer_can_cancel_pending_payment_order(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $this->actingAs($buyer);

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => OrderStatus::CANCELLED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', OrderStatus::CANCELLED->value);

        $order->refresh();
        $this->assertEquals(OrderStatus::CANCELLED, $order->status);
    }

    public function test_buyer_cannot_advance_order_status(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $this->actingAs($buyer);

        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", [
            'status' => OrderStatus::PROCESSING->value,
        ]);

        $response->assertStatus(422);
    }

    public function test_non_owner_receives_403_on_order_show(): void
    {
        $owner = User::factory()->buyerB2b()->create();
        $otherBuyer = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherBuyer);

        $response = $this->getJson("/api/v1/orders/{$order->id}");

        $response->assertForbidden();
    }
}
