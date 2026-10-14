<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Direct coverage of the `visibleTo` query scopes: ownership is resolved through
 * the correct chain (invoice → order, payment → invoice → order) and SUPERADMIN
 * sees every record, matching the policies.
 */
class AccountScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_rfq_and_order_scopes_isolate_buyers_and_open_for_superadmin(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();
        $admin = User::factory()->superadmin()->create();

        Rfq::factory()->count(2)->create(['user_id' => $buyer->id]);
        Rfq::factory()->count(3)->create(['user_id' => $other->id]);
        Order::factory()->count(1)->create(['user_id' => $buyer->id]);
        Order::factory()->count(4)->create(['user_id' => $other->id]);

        $this->assertSame(2, Rfq::query()->visibleTo($buyer)->count());
        $this->assertSame(5, Rfq::query()->visibleTo($admin)->count());
        $this->assertSame(1, Order::query()->visibleTo($buyer)->count());
        $this->assertSame(5, Order::query()->visibleTo($admin)->count());
    }

    public function test_invoice_and_payment_scopes_resolve_through_ownership_chain(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();
        $admin = User::factory()->superadmin()->create();

        $buyerOrder = Order::factory()->create(['user_id' => $buyer->id]);
        $otherOrder = Order::factory()->create(['user_id' => $other->id]);

        $buyerInvoice = Invoice::factory()->create(['order_id' => $buyerOrder->id]);
        $otherInvoice = Invoice::factory()->create(['order_id' => $otherOrder->id]);

        Payment::factory()->create(['invoice_id' => $buyerInvoice->id]);
        Payment::factory()->count(2)->create(['invoice_id' => $otherInvoice->id]);

        $this->assertSame(1, Invoice::query()->visibleTo($buyer)->count());
        $this->assertSame(2, Invoice::query()->visibleTo($admin)->count());
        $this->assertSame(1, Payment::query()->visibleTo($buyer)->count());
        $this->assertSame(3, Payment::query()->visibleTo($admin)->count());
    }
}
