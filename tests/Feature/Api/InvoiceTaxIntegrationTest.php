<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TaxApplicability;
use App\Models\AuditLog;
use App\Models\FakturCode;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\TaxRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * End-to-end coverage for the Phase 2E tax pipeline:
 * Stage-1 context capture -> BAST sign tax computation -> REVIEW_REQUIRED
 * hold -> superadmin recalculate-tax -> authoritative resolution.
 *
 * The frozen default TaxRuleFactory row is the v1 CONFIRMED rule effective
 * 2025-02-04..2025-07-31, so the frozen '2025-05-15' window resolves and the
 * '2025-08-15' window holds (no CONFIRMED rule matches that window).
 */
class InvoiceTaxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2025-05-15');

        FakturCode::factory()->create(['code' => '01']);

        TaxRule::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Create a single-line order (base 100000 x 2), drive it to DELIVERED
     * as superadmin, then leave the request actor as the buyer ready to sign.
     *
     * @return array{buyer: User, superadmin: User, order: Order}
     */
    private function deliveredOrder(bool $withContext = true): array
    {
        $buyer = User::factory()->buyerB2b()->create();
        $superadmin = User::factory()->superadmin()->create();
        $product = Product::factory()->create([
            'base_price' => 100000.00,
            'tax_rate_percentage' => 11.00,
        ]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $item = OrderItem::factory();
        $item = $withContext ? $item->withCommercialContext() : $item;
        $item->create([
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

        return [$buyer, $superadmin, $order];
    }

    public function test_bast_sign_resolves_tax_authoritatively_for_frozen_context(): void
    {
        [$buyer, $superadmin, $order] = $this->deliveredOrder();

        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertNotNull($invoice);

        $this->assertSame(InvoiceStatus::UNPAID, $invoice->status);
        $this->assertSame('22000.00', $invoice->ppn_amount);
        $this->assertSame('222000.00', $invoice->grand_total);
        $this->assertSame('222000.00', $invoice->amount_due);
        $this->assertSame('1.0', $invoice->tax_calculation_version);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::UNPAID->value,
            'tax_calculation_version' => '1.0',
        ]);

        $snapshot = $invoice->ruleSnapshots()->first();
        $this->assertNotNull($snapshot);
        $this->assertSame('TAX-PPN-01', $snapshot->rule_code);
        $this->assertSame('01', $snapshot->faktur_code);
        $this->assertSame('183333.33', $snapshot->dpp_amount);

        $this->assertDatabaseHas('invoice_rule_snapshots', [
            'invoice_id' => $invoice->id,
            'rule_snapshot_id' => $snapshot->id,
            'tax_amount' => 22000.00,
        ]);

        $this->actingAs($superadmin);

        $this->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.tax_calculated', true)
            ->assertJsonPath('data.tax_calculation_version', '1.0')
            ->assertJsonPath('data.faktur_code', '01')
            ->assertJsonPath('data.rule_snapshots.0.rule_code', 'TAX-PPN-01')
            ->assertJsonPath('data.rule_snapshots.0.faktur_code', '01')
            ->assertJsonPath('data.rule_snapshots.0.dpp_amount', '183333.33')
            ->assertJsonPath('data.rule_snapshots.0.tax_amount', '22000.00');
    }

    public function test_bast_sign_holds_invoice_when_commercial_context_is_missing(): void
    {
        [$buyer, $superadmin, $order] = $this->deliveredOrder(withContext: false);

        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertNotNull($invoice);

        $this->assertEquals(InvoiceStatus::REVIEW_REQUIRED, $invoice->status);
        $this->assertSame('0.00', $invoice->ppn_amount);
        $this->assertSame('0.00', $invoice->grand_total);
        $this->assertNull($invoice->tax_calculation_version);

        $this->assertDatabaseCount('rule_snapshots', 0);
        $this->assertDatabaseCount('invoice_rule_snapshots', 0);

        $log = AuditLog::where('action', AuditAction::TAX_RESOLUTION_REVIEW_REQUIRED->value)
            ->where('entity_id', $invoice->id)
            ->firstOrFail();
        $this->assertSame('NO_COMMERCIAL_CONTEXT', $log->new_state['hold_reason_code']);
        $this->assertSame(0, $log->new_state['resolved_lines']);
        $this->assertSame(1, $log->new_state['total_lines']);

        $this->actingAs($superadmin);

        $this->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.tax_calculated', false)
            ->assertJsonPath('data.payment_status', InvoiceStatus::REVIEW_REQUIRED->value);
    }

    public function test_bast_sign_holds_invoice_when_rule_requires_review(): void
    {
        Carbon::setTestNow('2025-08-15');

        TaxRule::factory()->create([
            'rule_version' => 'v2',
            'effective_from' => '2025-08-01',
            'effective_until' => null,
            'applicability' => TaxApplicability::REVIEW_REQUIRED->value,
        ]);

        [, , $order] = $this->deliveredOrder();
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertEquals(InvoiceStatus::REVIEW_REQUIRED, $invoice->status);

        $log = AuditLog::where('action', AuditAction::TAX_RESOLUTION_REVIEW_REQUIRED->value)
            ->where('entity_id', $invoice->id)
            ->firstOrFail();
        $this->assertSame('APPLICABILITY_REVIEW_REQUIRED', $log->new_state['hold_reason_code']);
    }

    public function test_recalculate_tax_resolves_hold_when_rule_becomes_available(): void
    {
        Carbon::setTestNow('2025-08-15');

        // No CONFIRMED rule covers the 2025-08 window yet -> hold on sign.
        [, $superadmin, $order] = $this->deliveredOrder();
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertEquals(InvoiceStatus::REVIEW_REQUIRED, $invoice->status);

        // Publish a CONFIRMED v3 rule effective 2025-08-01 onward.
        TaxRule::factory()->create([
            'rule_version' => 'v3',
            'effective_from' => '2025-08-01',
            'effective_until' => null,
        ]);

        $this->actingAs($superadmin);

        $this->postJson("/api/v1/invoices/{$invoice->id}/recalculate-tax")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', InvoiceStatus::UNPAID->value)
            ->assertJsonPath('data.tax_calculated', true);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::UNPAID, $invoice->status);
        $this->assertSame('22000.00', $invoice->ppn_amount);
        $this->assertSame('222000.00', $invoice->grand_total);
        $this->assertSame('1.0', $invoice->tax_calculation_version);

        $this->assertDatabaseHas('invoice_rule_snapshots', [
            'invoice_id' => $invoice->id,
        ]);

        $log = AuditLog::where('action', AuditAction::INVOICE_TAX_RECALCULATED->value)
            ->where('entity_id', $invoice->id)
            ->firstOrFail();
        $this->assertSame(InvoiceStatus::REVIEW_REQUIRED->value, $log->previous_state['status']);
        $this->assertSame(InvoiceStatus::UNPAID->value, $log->new_state['status']);

        // W1 hardening: the audit state snapshot must also capture the tax
        // computation fields so a recalc is fully reconstructible from the
        // audit trail alone.
        $this->assertSame('200000.00', $log->previous_state['subtotal']);
        $this->assertSame('0.00', $log->previous_state['ppn_amount']);
        $this->assertNull($log->previous_state['tax_calculation_version']);

        $this->assertSame('200000.00', $log->new_state['subtotal']);
        $this->assertSame('22000.00', $log->new_state['ppn_amount']);
        $this->assertSame('1.0', $log->new_state['tax_calculation_version']);
    }

    public function test_failed_recalculate_leaves_hold_untouched(): void
    {
        Carbon::setTestNow('2025-08-15');

        [, $superadmin, $order] = $this->deliveredOrder();
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertEquals(InvoiceStatus::REVIEW_REQUIRED, $invoice->status);

        // A REVIEW_REQUIRED v3 rule keeps the computation on hold.
        TaxRule::factory()->create([
            'rule_version' => 'v3',
            'effective_from' => '2025-08-01',
            'effective_until' => null,
            'applicability' => TaxApplicability::REVIEW_REQUIRED->value,
        ]);

        $this->actingAs($superadmin);

        $this->postJson("/api/v1/invoices/{$invoice->id}/recalculate-tax")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.invoice_id.0', 'APPLICABILITY_REVIEW_REQUIRED');

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::REVIEW_REQUIRED, $invoice->status);
        $this->assertDatabaseCount('rule_snapshots', 0);
        $this->assertDatabaseCount('invoice_rule_snapshots', 0);
    }

    public function test_recalculate_tax_is_rejected_when_invoice_is_not_on_hold(): void
    {
        [, $superadmin, $order] = $this->deliveredOrder();
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertEquals(InvoiceStatus::UNPAID, $invoice->status);

        $this->actingAs($superadmin);

        $this->postJson("/api/v1/invoices/{$invoice->id}/recalculate-tax")
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.invoice_id.0', 'Invoice tidak berstatus REVIEW_REQUIRED.');
    }

    public function test_buyer_cannot_recalculate_tax(): void
    {
        [$buyer, , $order] = $this->deliveredOrder(withContext: false);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertEquals(InvoiceStatus::REVIEW_REQUIRED, $invoice->status);

        $this->actingAs($buyer);

        $this->postJson("/api/v1/invoices/{$invoice->id}/recalculate-tax")->assertForbidden();
    }

    public function test_payment_submission_is_blocked_on_review_required_invoice(): void
    {
        Storage::fake('documents');

        [, , $order] = $this->deliveredOrder(withContext: false);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [
            'amount' => 222000.00,
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            'payment_date' => now()->toDateString(),
            'proof_file' => UploadedFile::fake()->image('proof.jpg', 600, 800),
            'notes' => 'Pembayaran via BCA',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.invoice_id.0', 'Invoice berstatus REVIEW_REQUIRED dan belum dapat menerima pembayaran.');
    }

    public function test_review_required_invoice_rejects_manual_status_changes(): void
    {
        [, $superadmin, $order] = $this->deliveredOrder(withContext: false);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/invoices/{$invoice->id}/payment-status", [
            'payment_status' => InvoiceStatus::UNPAID->value,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_review_required_invoice_is_excluded_from_overdue_scan(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::REVIEW_REQUIRED,
            'issued_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:check-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::REVIEW_REQUIRED->value,
        ]);
    }

    public function test_resolved_invoice_keeps_informational_pph_withholding(): void
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

        OrderItem::factory()->withCommercialContext()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $this->actingAs($superadmin);
        foreach ([OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $status) {
            $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => $status->value])->assertOk();
        }

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertNotNull($invoice);
        $this->assertSame('3000.00', $invoice->pph_amount);
        $this->assertSame('22000.00', $invoice->ppn_amount);
        $this->assertSame('222000.00', $invoice->grand_total);
    }
}
