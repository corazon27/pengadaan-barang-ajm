<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\BastStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentTerm;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function deliveredOrderWithBast(): array
    {
        $buyer = User::factory()->buyerB2b()->create();
        $superadmin = User::factory()->superadmin()->create();
        $product = Product::factory()->create(['base_price' => 100000.00, 'tax_rate_percentage' => 11.00]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($superadmin);

        foreach ([OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $status) {
            $this->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => $status->value,
            ])->assertOk();
        }

        $order->refresh();
        $bast = $order->bastDocument;

        return [$buyer, $superadmin, $order, $bast];
    }

    public function test_signing_bast_completes_order_and_generates_invoice(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $this->actingAs($buyer);

        $response = $this->postJson("/api/v1/orders/{$order->id}/bast/sign", [
            'notes' => 'Barang diterima lengkap',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', BastStatus::SIGNED->value);

        $bast->refresh();
        $order->refresh();

        $this->assertEquals(BastStatus::SIGNED, $bast->status);
        $this->assertEquals(OrderStatus::COMPLETED, $order->status);
        $this->assertNotNull($bast->signed_by);
        $this->assertNotNull($bast->signed_at);
        $this->assertSame('Barang diterima lengkap', $bast->notes);

        $invoice = $order->invoices()->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(InvoiceStatus::UNPAID, $invoice->status);
        $this->assertSame('200000.00', $invoice->subtotal);
        $this->assertSame('22000.00', $invoice->ppn_amount);
        $this->assertSame('0.00', $invoice->pph_amount);
        $this->assertSame('222000.00', $invoice->grand_total);
        $this->assertSame('222000.00', $invoice->amount_due);
        $this->assertEquals(PaymentTerm::TOP_30, $invoice->payment_term);

        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'bast_id' => $bast->id,
            'status' => InvoiceStatus::UNPAID->value,
            'subtotal' => 200000.00,
            'ppn_amount' => 22000.00,
            'pph_amount' => 0.00,
            'grand_total' => 222000.00,
        ]);
    }

    public function test_invoice_uses_snapshotted_rates_and_title_after_catalog_change(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $superadmin = User::factory()->superadmin()->create();
        $product = Product::factory()->create([
            'title' => 'Laptop Kantor',
            'base_price' => 100000.00,
            'tax_rate_percentage' => 11.00,
            'pph_rate_percentage' => 2.00,
        ]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        // Catalog changes after the order must not leak into the invoice.
        $product->update([
            'title' => 'Laptop Kantor (Edisi Baru)',
            'tax_rate_percentage' => 12.00,
            'pph_rate_percentage' => 0.00,
        ]);

        $this->actingAs($superadmin);

        foreach ([OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $status) {
            $this->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => $status->value,
            ])->assertOk();
        }

        $this->actingAs($buyer);

        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertNotNull($invoice);

        // Snapshot rates frozen at order time are used, not the updated catalog.
        $this->assertSame('200000.00', $invoice->subtotal);
        $this->assertSame('22000.00', $invoice->ppn_amount);
        $this->assertSame('4000.00', $invoice->pph_amount);
        $this->assertSame('222000.00', $invoice->grand_total);
        $this->assertSame('222000.00', $invoice->amount_due);
    }

    public function test_signing_bast_creates_exactly_one_invoice(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $this->actingAs($buyer);

        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $this->assertSame(1, $order->invoices()->count());

        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertStatus(422);

        $this->assertSame(1, $order->invoices()->count());
    }

    public function test_order_cannot_have_more_than_one_invoice(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $this->expectException(QueryException::class);

        Invoice::factory()->create([
            'order_id' => $order->id,
            'bast_id' => $bast->id,
        ]);
    }

    public function test_cannot_sign_bast_for_non_delivered_order(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $superadmin = User::factory()->superadmin()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::SHIPPED,
        ]);
        $bast = $order->bastDocument()->create([
            'bast_number' => 'BAST-PENDING',
            'status' => BastStatus::PENDING_SIGNATURE,
        ]);

        $this->actingAs($buyer);

        $response = $this->postJson("/api/v1/orders/{$order->id}/bast/sign");

        $response->assertStatus(422)
            ->assertJsonPath('errors.order_status.0', 'Pesanan harus berstatus DELIVERED untuk menandatangani BAST.');
    }

    public function test_cannot_sign_already_signed_bast(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $superadmin = User::factory()->superadmin()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::DELIVERED,
        ]);
        $bast = $order->bastDocument()->create([
            'bast_number' => 'BAST-SIGNED',
            'status' => BastStatus::SIGNED,
            'signed_by' => $buyer->id,
            'signed_at' => now(),
            'signed_date' => now()->toDateString(),
        ]);

        $this->actingAs($buyer);

        $response = $this->postJson("/api/v1/orders/{$order->id}/bast/sign");

        $response->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'BAST sudah berstatus SIGNED.');
    }

    public function test_non_owner_cannot_sign_bast(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $otherBuyer = User::factory()->buyerB2b()->create();
        $this->actingAs($otherBuyer);

        $response = $this->postJson("/api/v1/orders/{$order->id}/bast/sign");

        $response->assertForbidden();
    }

    public function test_superadmin_can_update_invoice_payment_status_to_paid(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->actingAs($superadmin);

        $response = $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => InvoiceStatus::PAID->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', InvoiceStatus::PAID->value);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_buyer_cannot_update_invoice_payment_status(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->actingAs($buyer);

        $response = $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => InvoiceStatus::PAID->value,
        ]);

        $response->assertForbidden();
    }

    public function test_invalid_payment_status_returns_422(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->actingAs($superadmin);

        $response = $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => 'REFUNDED',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_paid_invoice_cannot_revert_to_earlier_status(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => InvoiceStatus::PAID->value,
        ])->assertOk();

        // PAID is terminal: no transition back to UNPAID / PARTIALLY_PAID / OVERDUE.
        foreach ([InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE] as $status) {
            $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
                'payment_status' => $status->value,
            ])->assertStatus(422)
                ->assertJsonPath('success', false)
                ->assertJsonPath('data', null);
        }

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PAID, $invoice->status);

        // Idempotent re-application of PAID is allowed.
        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => InvoiceStatus::PAID->value,
        ])->assertOk();
    }

    public function test_overdue_invoice_cannot_revert_to_unpaid(): void
    {
        [$buyer, $superadmin, $order, $bast] = $this->deliveredOrderWithBast();

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => InvoiceStatus::OVERDUE->value,
        ])->assertOk();

        // OVERDUE may move forward (partial / paid) but not regress to UNPAID.
        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => InvoiceStatus::UNPAID->value,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => InvoiceStatus::PARTIALLY_PAID->value,
        ])->assertOk();
    }

    public function test_invoice_records_pph_withholding_without_adding_to_grand_total(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $superadmin = User::factory()->superadmin()->create();
        $product = Product::factory()->create([
            'base_price' => 100000.00,
            'tax_rate_percentage' => 11.00,
            'pph_rate_percentage' => 1.50,
        ]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($superadmin);

        foreach ([OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $status) {
            $this->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => $status->value,
            ])->assertOk();
        }

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertSame('200000.00', $invoice->subtotal);
        $this->assertSame('22000.00', $invoice->ppn_amount);
        $this->assertSame('3000.00', $invoice->pph_amount);
        $this->assertSame('222000.00', $invoice->grand_total);
        $this->assertSame('222000.00', $invoice->amount_due);
    }

    public function test_order_accepts_zero_top_days_for_immediate_payment(): void
    {
        $order = Order::factory()->create(['top_days' => 0]);

        $this->assertSame(0, $order->top_days);
    }

    public function test_immediate_payment_term_sets_due_date_on_issued_date(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $superadmin = User::factory()->superadmin()->create();
        $product = Product::factory()->create(['base_price' => 100000.00, 'tax_rate_percentage' => 11.00]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'top_days' => 0,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($superadmin);

        foreach ([OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $status) {
            $this->patchJson("/api/v1/orders/{$order->id}/status", [
                'status' => $status->value,
            ])->assertOk();
        }

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->assertEquals(PaymentTerm::IMMEDIATE, $invoice->payment_term);
        $this->assertSame($invoice->issued_date->toDateString(), $invoice->due_date->toDateString());
    }

    public function test_invoice_listing_is_scoped_by_role(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer1 = User::factory()->buyerB2b()->create();
        $buyer2 = User::factory()->buyerB2b()->create();

        $invoice1 = Invoice::factory()->create(['order_id' => Order::factory()->create(['user_id' => $buyer1->id])->id]);
        Invoice::factory()->create(['order_id' => Order::factory()->create(['user_id' => $buyer2->id])->id]);

        $this->actingAs($buyer1);

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $invoice1->id)
            ->assertJsonCount(1, 'data');

        $this->actingAs($superadmin);

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
