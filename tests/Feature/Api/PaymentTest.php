<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PaymentVerifiedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function unpaidInvoice(): array
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

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();

        $this->actingAs($buyer);

        return [$buyer, $superadmin, $invoice];
    }

    private function submitPaymentPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 222000.00,
            'payment_method' => PaymentMethod::BANK_TRANSFER->value,
            'payment_date' => now()->toDateString(),
            'proof_file' => UploadedFile::fake()->image('proof.jpg', 600, 800),
            'notes' => 'Pembayaran via BCA',
        ], $overrides);
    }

    public function test_buyer_submits_payment_with_proof_file(): void
    {
        Storage::fake('documents');
        [$buyer, , $invoice] = $this->unpaidInvoice();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount', '222000.00')
            ->assertJsonPath('data.payment_method', PaymentMethod::BANK_TRANSFER->value)
            ->assertJsonPath('data.status', PaymentStatus::PENDING_VERIFICATION->value)
            ->assertJsonPath('data.status_label', 'Menunggu Verifikasi')
            ->assertJsonPath('data.user_id', $buyer->id);

        $storedPath = $response->json('data.proof_file_url');
        $this->assertNotNull($storedPath);
        $this->assertStringStartsWith('payments/proofs/', $storedPath);

        Storage::disk('documents')->assertExists($storedPath);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 222000.00,
            'status' => PaymentStatus::PENDING_VERIFICATION->value,
        ]);
    }

    public function test_payment_proof_is_not_exposed_on_public_disk(): void
    {
        Storage::fake('documents');
        [$buyer, , $invoice] = $this->unpaidInvoice();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload());
        $response->assertCreated();

        $storedPath = $response->json('data.proof_file_url');

        Storage::disk('documents')->assertExists($storedPath);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_owner_can_download_payment_proof(): void
    {
        Storage::fake('documents');
        [$buyer, , $invoice] = $this->unpaidInvoice();

        $submission = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload());
        $paymentId = $submission->json('data.id');
        $storedPath = $submission->json('data.proof_file_url');

        $this->actingAs($buyer);

        $response = $this->get("/api/v1/payments/{$paymentId}/proof");

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->assertSame(
            Storage::disk('documents')->get($storedPath),
            $response->streamedContent()
        );
    }

    public function test_superadmin_can_download_any_payment_proof(): void
    {
        Storage::fake('documents');
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $submission = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload());
        $paymentId = $submission->json('data.id');

        $this->actingAs($superadmin);

        $this->get("/api/v1/payments/{$paymentId}/proof")->assertOk();
    }

    public function test_other_buyer_cannot_download_payment_proof(): void
    {
        Storage::fake('documents');
        [$buyer, , $invoice] = $this->unpaidInvoice();

        $submission = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload());
        $paymentId = $submission->json('data.id');

        $otherBuyer = User::factory()->buyerB2b()->create();
        $this->actingAs($otherBuyer);

        $this->getJson("/api/v1/payments/{$paymentId}/proof")->assertForbidden();
    }

    public function test_payment_proof_download_requires_authentication(): void
    {
        Storage::fake('documents');
        [$buyer, , $invoice] = $this->unpaidInvoice();

        $submission = $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload());
        $paymentId = $submission->json('data.id');

        $this->app['auth']->forgetGuards();

        $this->getJson("/api/v1/payments/{$paymentId}/proof")->assertUnauthorized();
    }

    public function test_payment_proof_download_returns_404_when_file_missing(): void
    {
        Storage::fake('documents');
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 50000.00,
            'proof_file_url' => 'payments/proofs/missing-proof.jpg',
        ]);

        $this->actingAs($superadmin);

        $this->getJson("/api/v1/payments/{$payment->id}/proof")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_payment_validation_rejects_invalid_inputs(): void
    {
        [$buyer, , $invoice] = $this->unpaidInvoice();

        // Zero amount
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload([
            'amount' => 0,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        // Negative amount
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload([
            'amount' => -100,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        // Invalid payment method
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload([
            'payment_method' => 'CRYPTO',
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('payment_method');

        // Invalid file type (text file)
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload([
            'proof_file' => UploadedFile::fake()->create('proof.txt', 100, 'text/plain'),
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('proof_file');

        // Oversized file (> 5MB)
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload([
            'proof_file' => UploadedFile::fake()->create('proof.pdf', 6000, 'application/pdf'),
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('proof_file');

        // Missing file
        $payload = $this->submitPaymentPayload();
        unset($payload['proof_file']);
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('proof_file');
    }

    public function test_buyer_cannot_submit_payment_for_another_buyers_invoice(): void
    {
        [, , $invoice] = $this->unpaidInvoice();
        $otherBuyer = User::factory()->buyerB2b()->create();

        $this->actingAs($otherBuyer);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload())
            ->assertForbidden();
    }

    public function test_superadmin_verifies_full_payment_and_marks_invoice_paid(): void
    {
        Notification::fake();

        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 222000.00,
        ]);

        $this->actingAs($superadmin);

        $response = $this->patchJson("/api/v1/payments/{$payment->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', PaymentStatus::VERIFIED->value);

        $payment->refresh();
        $invoice->refresh();

        $this->assertEquals(PaymentStatus::VERIFIED, $payment->status);
        $this->assertNotNull($payment->verified_by);
        $this->assertNotNull($payment->verified_at);
        $this->assertNull($payment->rejection_reason);
        $this->assertEquals(InvoiceStatus::PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        Notification::assertSentTo($buyer, PaymentVerifiedNotification::class, function ($notification) {
            return $notification->invoice->status === InvoiceStatus::PAID;
        });
    }

    public function test_superadmin_verifies_partial_payment_marks_invoice_partially_paid(): void
    {
        Notification::fake();

        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 100000.00,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$payment->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertOk();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID, $invoice->status);
        $this->assertNull($invoice->paid_at);

        Notification::assertSentTo($buyer, PaymentVerifiedNotification::class, function ($notification) {
            return $notification->invoice->status === InvoiceStatus::PARTIALLY_PAID;
        });
    }

    public function test_superadmin_verifies_multiple_payments_accumulate_to_paid(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $first = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 100000.00,
        ]);
        $second = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 122000.00,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$first->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertOk();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID, $invoice->status);

        $this->patchJson("/api/v1/payments/{$second->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertOk();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_verifying_payment_over_invoice_total_is_rejected(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 222001.00,
        ]);

        $this->actingAs($superadmin);

        $response = $this->patchJson("/api/v1/payments/{$payment->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('errors.amount.0', 'Total pembayaran terverifikasi Rp222001.00 melebihi tagihan Rp222000.00.');

        // The payment stays pending and the invoice is untouched.
        $payment->refresh();
        $invoice->refresh();

        $this->assertEquals(PaymentStatus::PENDING_VERIFICATION, $payment->status);
        $this->assertNull($payment->verified_by);
        $this->assertEquals(InvoiceStatus::UNPAID, $invoice->status);
    }

    public function test_verifying_partial_then_overpayment_is_rejected(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $first = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 100000.00,
        ]);
        $overpay = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 122001.00,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$first->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertOk();

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID, $invoice->status);

        // 100000 + 122001 > 222000 -> overpayment must be rejected.
        $this->patchJson("/api/v1/payments/{$overpay->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);

        $overpay->refresh();
        $this->assertEquals(PaymentStatus::PENDING_VERIFICATION, $overpay->status);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID, $invoice->status);
    }

    public function test_verifying_partial_then_exact_balance_is_accepted(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $first = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 100000.00,
        ]);
        $settle = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 122000.00,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$first->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertOk();

        $this->patchJson("/api/v1/payments/{$settle->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertOk();

        $settle->refresh();
        $invoice->refresh();

        $this->assertEquals(PaymentStatus::VERIFIED, $settle->status);
        $this->assertEquals(InvoiceStatus::PAID, $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_rejecting_an_overpaying_payment_is_still_allowed(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 222001.00,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$payment->id}/verify", [
            'status' => PaymentStatus::REJECTED->value,
            'rejection_reason' => 'Jumlah melebihi tagihan.',
        ])->assertOk();

        $payment->refresh();
        $this->assertEquals(PaymentStatus::REJECTED, $payment->status);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::UNPAID, $invoice->status);
    }

    public function test_superadmin_rejects_payment_with_reason(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 222000.00,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$payment->id}/verify", [
            'status' => PaymentStatus::REJECTED->value,
            'rejection_reason' => 'Bukti tidak terbaca.',
        ])->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::REJECTED->value);

        $payment->refresh();
        $invoice->refresh();

        $this->assertEquals(PaymentStatus::REJECTED, $payment->status);
        $this->assertSame('Bukti tidak terbaca.', $payment->rejection_reason);
        $this->assertEquals(InvoiceStatus::UNPAID, $invoice->status);
        $this->assertNull($invoice->paid_at);
    }

    public function test_rejecting_payment_requires_rejection_reason(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 222000.00,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$payment->id}/verify", [
            'status' => PaymentStatus::REJECTED->value,
        ])->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_cannot_verify_already_verified_payment(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->verified()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 222000.00,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/payments/{$payment->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_non_superadmin_cannot_verify_payment(): void
    {
        [$buyer, , $invoice] = $this->unpaidInvoice();

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 222000.00,
        ]);

        $this->actingAs($buyer);

        $this->patchJson("/api/v1/payments/{$payment->id}/verify", [
            'status' => PaymentStatus::VERIFIED->value,
        ])->assertForbidden();
    }

    public function test_buyer_cannot_submit_payment_for_paid_invoice(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        $invoice->update([
            'status' => InvoiceStatus::PAID,
            'paid_at' => now(),
        ]);

        $this->actingAs($buyer);

        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", $this->submitPaymentPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_superadmin_lists_payments_with_status_filter(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 50000.00,
        ]);
        Payment::factory()->verified()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 100000.00,
        ]);

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/v1/payments?status='.PaymentStatus::VERIFIED->value)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', PaymentStatus::VERIFIED->value);
    }

    public function test_buyer_sees_only_own_payments(): void
    {
        [$buyer, , $invoice] = $this->unpaidInvoice();
        $otherBuyer = User::factory()->buyerB2b()->create();
        $otherOrder = Order::factory()->create(['user_id' => $otherBuyer->id]);
        $otherInvoice = Invoice::factory()->create(['order_id' => $otherOrder->id]);

        Payment::factory()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 50000.00,
        ]);
        Payment::factory()->create([
            'invoice_id' => $otherInvoice->id,
            'user_id' => $otherBuyer->id,
            'amount' => 100000.00,
        ]);

        $this->actingAs($buyer);

        $this->getJson('/api/v1/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_id', $invoice->id);
    }

    public function test_invoice_resource_includes_payments_breakdown(): void
    {
        [$buyer, $superadmin, $invoice] = $this->unpaidInvoice();

        Payment::factory()->verified()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 100000.00,
        ]);
        Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'user_id' => $buyer->id,
            'amount' => 50000.00,
        ]);

        $this->actingAs($buyer);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk();

        $response->assertJsonPath('data.subtotal', '200000.00')
            ->assertJsonPath('data.ppn_amount', '22000.00')
            ->assertJsonPath('data.pph_amount', '0.00')
            ->assertJsonPath('data.grand_total', '222000.00')
            ->assertJsonPath('data.payment_term', 'TOP_30')
            ->assertJsonPath('data.payment_term_label', 'TOP 30 Hari')
            ->assertJsonPath('data.paid_amount', '100000.00')
            ->assertJsonCount(2, 'data.payments');
    }
}
