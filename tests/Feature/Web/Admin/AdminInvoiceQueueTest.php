<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Admin;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminInvoiceQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_invoices_and_can_filter_by_status(): void
    {
        $admin = User::factory()->superadmin()->create();

        Invoice::factory()->count(2)->create(['status' => InvoiceStatus::REVIEW_REQUIRED]);
        Invoice::factory()->count(3)->create(['status' => InvoiceStatus::UNPAID]);

        $this->actingAs($admin)
            ->get('/admin/tagihan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Invoices/Index')
                ->where('pagination.total', 5)
                ->where('pagination.baseUrl', '/admin/tagihan'));

        $this->actingAs($admin)
            ->get('/admin/tagihan?status=REVIEW_REQUIRED')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 2));
    }

    public function test_invoice_detail_never_exposes_document_paths(): void
    {
        $admin = User::factory()->superadmin()->create();
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-ADMIN-1',
            'invoice_pdf_url' => 'https://private.example.com/invoice.pdf',
            'faktur_pajak_url' => 'https://private.example.com/faktur.pdf',
        ]);

        $this->actingAs($admin)
            ->get("/admin/tagihan/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Invoices/Show')
                ->where('invoice.invoice_number', 'INV-ADMIN-1')
                ->missing('invoice.invoice_pdf_url')
                ->missing('invoice.faktur_pajak_url'));
    }

    public function test_buyer_is_forbidden_from_admin_invoice_surfaces(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $invoice = Invoice::factory()->create();

        $this->actingAs($buyer)->get('/admin/tagihan')->assertForbidden();
        $this->actingAs($buyer)->get("/admin/tagihan/{$invoice->id}")->assertForbidden();
    }
}
