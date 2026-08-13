<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\BastStatus;
use App\Enums\OrderStatus;
use App\Enums\RfqStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_quotation_pdf_is_generated_when_superadmin_responds(): void
    {
        Storage::fake('documents');

        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();

        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::SUBMITTED]);
        $product = Product::factory()->create(['title' => 'Laptop Dell Latitude', 'base_price' => 12000000.00]);
        $rfqItem = RfqItem::factory()->create([
            'rfq_id' => $rfq->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->actingAs($superadmin);

        $response = $this->postJson("/api/v1/rfqs/{$rfq->id}/respond", [
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [
                [
                    'rfq_item_id' => $rfqItem->id,
                    'offered_price' => 11000000.00,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', RfqStatus::QUOTED->value);

        $rfq->refresh();
        $this->assertNotNull($rfq->quotation_pdf_url);
        $this->assertTrue(Storage::disk('documents')->exists($rfq->quotation_pdf_url));
        $this->assertStringStartsWith('%PDF', Storage::disk('documents')->get($rfq->quotation_pdf_url));
    }

    public function test_bast_draft_pdf_is_generated_when_order_is_shipped(): void
    {
        Storage::fake('documents');

        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create(['title' => 'Laptop Dell Latitude', 'base_price' => 12000000.00]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PENDING_PAYMENT,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::PROCESSING->value])->assertOk();
        $response = $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => OrderStatus::SHIPPED->value]);

        $response->assertOk()
            ->assertJsonPath('data.status', OrderStatus::SHIPPED->value);

        $bast = $order->bastDocument()->first();
        $this->assertNotNull($bast);
        $this->assertEquals(BastStatus::PENDING_SIGNATURE, $bast->status);
        $this->assertNotNull($bast->bast_document_url);
        $this->assertTrue(Storage::disk('documents')->exists($bast->bast_document_url));
        $this->assertStringStartsWith('%PDF', Storage::disk('documents')->get($bast->bast_document_url));
    }

    public function test_invoice_pdf_is_generated_when_bast_is_signed(): void
    {
        Storage::fake('documents');

        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create(['title' => 'Laptop Dell Latitude', 'base_price' => 100000.00, 'tax_rate_percentage' => 11.00]);

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
            $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => $status->value])->assertOk();
        }

        $bast = $order->bastDocument()->first();
        $this->assertNotNull($bast);

        $this->actingAs($buyer);

        $response = $this->postJson("/api/v1/orders/{$order->id}/bast/sign");

        $response->assertOk()
            ->assertJsonPath('data.status', BastStatus::SIGNED->value);

        $invoice = $order->invoices()->first();
        $this->assertNotNull($invoice);
        $this->assertNotSame('', $invoice->invoice_pdf_url);
        $this->assertTrue(Storage::disk('documents')->exists($invoice->invoice_pdf_url));
        $this->assertStringStartsWith('%PDF', Storage::disk('documents')->get($invoice->invoice_pdf_url));
    }

    public function test_quotation_pdf_can_be_downloaded_by_owner(): void
    {
        Storage::fake('documents');

        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::QUOTED]);

        Storage::disk('documents')->put('rfq/sample.pdf', '%PDF-1.4 sample');
        $rfq->update(['quotation_pdf_url' => 'rfq/sample.pdf']);

        $this->actingAs($buyer);

        $response = $this->getJson("/api/v1/rfqs/{$rfq->id}/quotation.pdf");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.4 sample', $response->streamedContent());
    }

    public function test_quotation_pdf_download_returns_404_when_missing(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::SUBMITTED]);

        $this->actingAs($buyer);

        $response = $this->getJson("/api/v1/rfqs/{$rfq->id}/quotation.pdf");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Dokumen belum tersedia.');
    }

    public function test_bast_pdf_download_returns_404_when_no_bast_exists(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'status' => OrderStatus::PROCESSING,
        ]);

        $this->actingAs($buyer);

        $response = $this->getJson("/api/v1/orders/{$order->id}/bast.pdf");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Dokumen belum tersedia.');
    }

    public function test_document_download_requires_authentication(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::QUOTED]);

        $response = $this->getJson("/api/v1/rfqs/{$rfq->id}/quotation.pdf");

        $response->assertUnauthorized();
    }

    public function test_document_download_is_forbidden_for_non_owner(): void
    {
        Storage::fake('documents');

        $owner = User::factory()->buyerB2b()->create();
        $otherBuyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $owner->id, 'status' => RfqStatus::QUOTED]);

        Storage::disk('documents')->put('rfq/sample.pdf', '%PDF-1.4 sample');
        $rfq->update(['quotation_pdf_url' => 'rfq/sample.pdf']);

        $this->actingAs($otherBuyer);

        $response = $this->getJson("/api/v1/rfqs/{$rfq->id}/quotation.pdf");

        $response->assertForbidden();
    }

    public function test_invoice_pdf_download_returns_404_when_url_empty(): void
    {
        Storage::fake('documents');

        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
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
            $this->patchJson("/api/v1/orders/{$order->id}/status", ['status' => $status->value])->assertOk();
        }

        $this->actingAs($buyer);
        $this->postJson("/api/v1/orders/{$order->id}/bast/sign")->assertOk();

        $invoice = $order->invoices()->first();
        $this->assertNotNull($invoice);
        $invoice->update(['invoice_pdf_url' => '']);

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertNotFound()
            ->assertJsonPath('message', 'Dokumen belum tersedia.');
    }

    public function test_company_config_has_complete_letterhead_data(): void
    {
        foreach (['name', 'legal_entity', 'nib', 'pkp', 'npwp', 'address', 'phone', 'email', 'website'] as $key) {
            $this->assertNotSame('', config('company.'.$key), "config('company.{$key}') must not be empty");
        }

        foreach (['name', 'account_name', 'account_number', 'branch'] as $key) {
            $this->assertNotSame('', config('company.bank.'.$key), "config('company.bank.{$key}') must not be empty");
        }
    }
}
