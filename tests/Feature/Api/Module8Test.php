<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\AuditAction;
use App\Enums\BastStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\RfqStatus;
use App\Models\AuditLog;
use App\Models\BastDocument;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Module8Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Drive an order through PROCESSING -> SHIPPED -> DELIVERED as superadmin
     * and return the order plus a BAST instance.
     *
     * @return array{superadmin: User, buyer: User, order: Order, bast: BastDocument}
     */
    private function shippedOrder(): array
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create(['base_price' => 100000.00]);

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
        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::PROCESSING->value])->assertOk();
        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::SHIPPED->value])->assertOk();

        $bast = $order->bastDocument()->first();
        $this->assertNotNull($bast);

        return [$superadmin, $buyer, $order, $bast];
    }

    public function test_audit_log_created_when_rfq_is_quoted(): void
    {
        Storage::fake('documents');

        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();

        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::SUBMITTED]);
        $rfqItem = RfqItem::factory()->create([
            'rfq_id' => $rfq->id,
            'product_id' => Product::factory()->create()->id,
            'quantity' => 3,
        ]);

        $this->actingAs($superadmin);

        $this->postJson("/api/v1/rfqs/{$rfq->id}/respond", [
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [
                ['rfq_item_id' => $rfqItem->id, 'offered_price' => 100000.00],
            ],
        ])->assertOk();

        $log = AuditLog::where('action', AuditAction::RFQ_QUOTED->value)->firstOrFail();

        $this->assertSame($superadmin->id, $log->user_id);
        $this->assertSame('Rfq', $log->entity_type);
        $this->assertSame($rfq->id, $log->entity_id);
        $this->assertSame(RfqStatus::SUBMITTED->value, $log->previous_state['status']);
        $this->assertSame(RfqStatus::QUOTED->value, $log->new_state['status']);
    }

    public function test_audit_logs_created_when_order_is_shipped(): void
    {
        Storage::fake('documents');

        [$superadmin, , $order, $bast] = $this->shippedOrder();

        $statusLog = AuditLog::where('action', AuditAction::ORDER_STATUS_UPDATED->value)
            ->where('entity_id', $order->id)
            ->where('new_state->status', OrderStatus::SHIPPED->value)
            ->firstOrFail();

        $this->assertSame($superadmin->id, $statusLog->user_id);
        $this->assertSame(OrderStatus::PROCESSING->value, $statusLog->previous_state['status']);
        $this->assertSame(OrderStatus::SHIPPED->value, $statusLog->new_state['status']);

        $bastLog = AuditLog::where('action', AuditAction::BAST_CREATED->value)->firstOrFail();

        $this->assertSame('BastDocument', $bastLog->entity_type);
        $this->assertSame($bast->id, $bastLog->entity_id);
    }

    public function test_audit_logs_created_when_bast_is_signed(): void
    {
        Storage::fake('documents');

        [$superadmin, $buyer, $order, $bast] = $this->shippedOrder();

        $this->actingAs($superadmin);
        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::DELIVERED->value])->assertOk();

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $signLog = AuditLog::where('action', AuditAction::BAST_SIGNED->value)->firstOrFail();
        $this->assertSame($buyer->id, $signLog->user_id);
        $this->assertSame(BastStatus::PENDING_SIGNATURE->value, $signLog->previous_state['status']);
        $this->assertSame(BastStatus::SIGNED->value, $signLog->new_state['status']);

        $completeLog = AuditLog::where('action', AuditAction::ORDER_STATUS_UPDATED->value)
            ->where('entity_id', $order->id)
            ->where('new_state->status', OrderStatus::COMPLETED->value)
            ->firstOrFail();
        $this->assertSame(OrderStatus::DELIVERED->value, $completeLog->previous_state['status']);
        $this->assertSame(OrderStatus::COMPLETED->value, $completeLog->new_state['status']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INVOICE_CREATED->value,
            'entity_type' => 'Invoice',
            'entity_id' => $order->invoices()->first()->id,
        ]);
    }

    public function test_audit_logs_created_when_payment_is_verified(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $superadmin = User::factory()->superadmin()->create();

        $invoice = Invoice::factory()->create(['amount_due' => 0, 'grand_total' => 0]);
        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 0,
            'status' => PaymentStatus::PENDING_VERIFICATION,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$payment->id}/verify", ['status' => PaymentStatus::VERIFIED->value])->assertOk();

        $paymentLog = AuditLog::where('action', AuditAction::PAYMENT_VERIFIED->value)->firstOrFail();
        $this->assertSame($superadmin->id, $paymentLog->user_id);
        $this->assertSame('Payment', $paymentLog->entity_type);
        $this->assertSame(PaymentStatus::PENDING_VERIFICATION->value, $paymentLog->previous_state['status']);
        $this->assertSame(PaymentStatus::VERIFIED->value, $paymentLog->new_state['status']);

        $invoiceLog = AuditLog::where('action', AuditAction::INVOICE_STATUS_UPDATED->value)
            ->where('entity_id', $invoice->id)
            ->firstOrFail();
        $this->assertSame(InvoiceStatus::UNPAID->value, $invoiceLog->previous_state['status']);
        $this->assertSame(InvoiceStatus::PAID->value, $invoiceLog->new_state['status']);
    }

    public function test_superadmin_can_list_audit_logs_with_filters(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        AuditLog::factory()->create([
            'user_id' => $superadmin->id,
            'action' => AuditAction::RFQ_QUOTED->value,
            'entity_type' => 'Rfq',
            'entity_id' => (string) Str::uuid(),
        ]);
        AuditLog::factory()->create([
            'user_id' => $superadmin->id,
            'action' => AuditAction::ORDER_CREATED->value,
            'entity_type' => 'Order',
            'entity_id' => (string) Str::uuid(),
        ]);

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    ['id', 'action', 'entity_type', 'entity_id', 'previous_state', 'new_state', 'created_at', 'user' => ['id', 'full_name', 'email']],
                ],
            ]);

        $this->getJson('/api/v1/audit-logs?action='.AuditAction::RFQ_QUOTED->value)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', AuditAction::RFQ_QUOTED->value);

        $this->getJson('/api/v1/audit-logs?entity_type=Order')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity_type', 'Order');
    }

    public function test_audit_log_listing_rejects_unknown_action(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/audit-logs?action=NOT_A_REAL_ACTION')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Validasi gagal.');
    }

    public function test_buyer_cannot_access_audit_logs(): void
    {
        $buyer = User::factory()->buyerB2b()->create();

        $this->actingAs($buyer);

        $this->getJson('/api/v1/audit-logs')->assertForbidden();
    }

    public function test_audit_logs_require_authentication(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
    }

    public function test_overdue_command_marks_past_due_invoices_and_logs(): void
    {
        // issued_date defaults to now(); MySQL enforces invoices_due_after_issued
        // (due_date >= issued_date), so simulate overdue invoices by dating the
        // issue 40 days back rather than pushing due_date into the past alone.
        $unpaid = Invoice::factory()->create([
            'status' => InvoiceStatus::UNPAID,
            'issued_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
        $partiallyPaid = Invoice::factory()->create([
            'status' => InvoiceStatus::PARTIALLY_PAID,
            'issued_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $paid = Invoice::factory()->create([
            'status' => InvoiceStatus::PAID,
            'issued_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
        ]);
        $future = Invoice::factory()->create([
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:check-overdue')
            ->expectsOutputToContain('2')
            ->assertExitCode(0);

        $this->assertDatabaseHas('invoices', [
            'id' => $unpaid->id,
            'status' => InvoiceStatus::OVERDUE->value,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $partiallyPaid->id,
            'status' => InvoiceStatus::OVERDUE->value,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $paid->id,
            'status' => InvoiceStatus::PAID->value,
        ]);
        $this->assertDatabaseHas('invoices', [
            'id' => $future->id,
            'status' => InvoiceStatus::UNPAID->value,
        ]);

        $this->assertDatabaseCount('audit_logs', 2);
        $logs = AuditLog::where('action', AuditAction::INVOICE_MARKED_OVERDUE->value)->get();

        $this->assertCount(2, $logs);
        $this->assertTrue($logs->contains(fn (AuditLog $log) => $log->entity_id === $unpaid->id));
        $this->assertTrue($logs->contains(fn (AuditLog $log) => $log->entity_id === $partiallyPaid->id));
        foreach ($logs as $log) {
            $this->assertNull($log->user_id);
            $this->assertSame(InvoiceStatus::OVERDUE->value, $log->new_state['status']);
        }
    }

    public function test_overdue_command_is_registered(): void
    {
        $this->assertArrayHasKey('invoices:check-overdue', Artisan::all());
    }

    public function test_overdue_command_is_idempotent_across_runs(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::UNPAID,
            'issued_date' => now()->subDays(40)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('invoices:check-overdue')->assertExitCode(0);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::OVERDUE->value,
        ]);
        $this->assertDatabaseCount('audit_logs', 1);

        // A second run must not re-process the already-overdue invoice.
        $this->artisan('invoices:check-overdue')
            ->expectsOutputToContain('0')
            ->assertExitCode(0);

        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_superadmin_can_view_analytics_dashboard(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();

        $productA = Product::factory()->create(['base_price' => 100.00, 'tkdn_percentage' => 60]);
        $productB = Product::factory()->create(['base_price' => 200.00, 'tkdn_percentage' => 40]);

        Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::SUBMITTED]);
        Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::SUBMITTED]);
        Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::QUOTED]);

        $shippedOrder = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::SHIPPED,
        ]);
        OrderItem::factory()->create([
            'order_id' => $shippedOrder->id,
            'product_id' => $productA->id,
            'quantity' => 5,
        ]);
        OrderItem::factory()->create([
            'order_id' => $shippedOrder->id,
            'product_id' => $productA->id,
            'quantity' => 5,
        ]);

        $deliveredOrder = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::DELIVERED,
        ]);
        OrderItem::factory()->create([
            'order_id' => $deliveredOrder->id,
            'product_id' => $productB->id,
            'quantity' => 2,
        ]);

        $paidOrder = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);

        $bast = BastDocument::factory()->create(['order_id' => $shippedOrder->id]);
        Invoice::factory()->create([
            'order_id' => $shippedOrder->id,
            'bast_id' => $bast->id,
            'amount_due' => 300.00,
            'grand_total' => 300.00,
            'subtotal' => 300.00,
            'status' => InvoiceStatus::UNPAID,
        ]);
        Invoice::factory()->create([
            'order_id' => $deliveredOrder->id,
            'bast_id' => BastDocument::factory()->create(['order_id' => $deliveredOrder->id])->id,
            'amount_due' => 500.00,
            'grand_total' => 500.00,
            'subtotal' => 500.00,
            'status' => InvoiceStatus::OVERDUE,
        ]);
        Invoice::factory()->create([
            'order_id' => $paidOrder->id,
            'bast_id' => BastDocument::factory()->create(['order_id' => $paidOrder->id])->id,
            'amount_due' => 100.00,
            'grand_total' => 100.00,
            'subtotal' => 100.00,
            'status' => InvoiceStatus::PAID,
        ]);

        Payment::factory()->verified()->create(['invoice_id' => $shippedOrder->invoices()->first()->id, 'amount' => 100.00]);
        Payment::factory()->verified()->create(['invoice_id' => $shippedOrder->invoices()->first()->id, 'amount' => 250.00]);
        Payment::factory()->pending()->create(['invoice_id' => $shippedOrder->invoices()->first()->id, 'amount' => 999.00]);

        $this->actingAs($superadmin);

        $response = $this->getJson('/api/v1/analytics/dashboard')->assertOk();

        $this->assertSame(3, $response->json('data.rfqs.total'));
        $this->assertSame(2, $response->json('data.rfqs.by_status.'.RfqStatus::SUBMITTED->value));
        $this->assertSame(1, $response->json('data.rfqs.by_status.'.RfqStatus::QUOTED->value));

        $this->assertSame(3, $response->json('data.orders.total_count'));
        $this->assertEqualsWithDelta(1400.0, $response->json('data.orders.total_value'), 0.001);
        $this->assertSame(1, $response->json('data.orders.by_status.'.OrderStatus::SHIPPED->value.'.count'));
        $this->assertEqualsWithDelta(1000.0, $response->json('data.orders.by_status.'.OrderStatus::SHIPPED->value.'.total_value'), 0.001);
        $this->assertEqualsWithDelta(400.0, $response->json('data.orders.by_status.'.OrderStatus::DELIVERED->value.'.total_value'), 0.001);

        $this->assertEqualsWithDelta(800.0, $response->json('data.outstanding_receivables.total'), 0.001);
        $this->assertSame(1, $response->json('data.outstanding_receivables.by_status.'.InvoiceStatus::UNPAID->value.'.count'));
        $this->assertEqualsWithDelta(300.0, $response->json('data.outstanding_receivables.by_status.'.InvoiceStatus::UNPAID->value.'.total'), 0.001);
        $this->assertEqualsWithDelta(500.0, $response->json('data.outstanding_receivables.by_status.'.InvoiceStatus::OVERDUE->value.'.total'), 0.001);

        $this->assertSame(2, $response->json('data.verified_payments.count'));
        $this->assertEqualsWithDelta(350.0, $response->json('data.verified_payments.total_amount'), 0.001);

        $this->assertEqualsWithDelta(56.6667, $response->json('data.tkdn_compliance.average_tkdn_percentage'), 0.01);
        $this->assertNotNull($response->json('data.generated_at'));
    }

    public function test_analytics_dashboard_reports_null_tkdn_when_no_items(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/analytics/dashboard')
            ->assertOk()
            ->assertJsonPath('data.tkdn_compliance.average_tkdn_percentage', null);
    }

    public function test_buyer_cannot_access_analytics_dashboard(): void
    {
        $buyer = User::factory()->buyerB2b()->create();

        $this->actingAs($buyer);

        $this->getJson('/api/v1/analytics/dashboard')->assertForbidden();
    }

    public function test_analytics_require_authentication(): void
    {
        $this->getJson('/api/v1/analytics/dashboard')->assertUnauthorized();
    }
}
