<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/akun/tagihan')->assertRedirect(route('login'));
    }

    public function test_index_lists_only_invoices_reachable_through_own_orders(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        $ownOrder = Order::factory()->create(['user_id' => $user->id]);
        Invoice::factory()->create(['order_id' => $ownOrder->id]);

        Order::factory()->count(3)->create(['user_id' => $other->id])
            ->each(fn (Order $order) => Invoice::factory()->create(['order_id' => $order->id]));

        $this->actingAs($user)
            ->get('/akun/tagihan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Invoices/Index')
                ->has('invoices', 1)
                ->where('pagination.total', 1)
                ->where('pagination.baseUrl', '/akun/tagihan'));
    }

    public function test_owner_can_view_invoice_with_payments_but_not_private_evidence(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create(['order_id' => $order->id]);

        Payment::factory()->verified()->create(['invoice_id' => $invoice->id]);
        Payment::factory()->pending()->create(['invoice_id' => $invoice->id]);

        $this->actingAs($user)
            ->get("/akun/tagihan/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Invoices/Show')
                ->where('invoice.invoice_number', $invoice->invoice_number)
                ->has('invoice.payments', 2)
                ->missing('invoice.payments.0.proof_file_url')
                ->missing('invoice.invoice_pdf_url')
                ->missing('invoice.faktur_pajak_url'));
    }

    public function test_cross_buyer_invoice_detail_is_forbidden(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        $order = Order::factory()->create(['user_id' => $other->id]);
        $invoice = Invoice::factory()->create(['order_id' => $order->id]);

        $this->actingAs($user)
            ->get("/akun/tagihan/{$invoice->id}")
            ->assertForbidden();
    }
}
