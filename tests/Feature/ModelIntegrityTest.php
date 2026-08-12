<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\RfqStatus;
use App\Enums\UserRole;
use App\Models\BastDocument;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_enum_casts_round_trip(): void
    {
        $user = User::factory()->superadmin()->create();

        $this->assertSame(UserRole::SUPERADMIN, $user->role);
        $this->assertSame(UserRole::SUPERADMIN->value, $user->getRawOriginal('role'));

        $rfq = Rfq::factory()->create();
        $this->assertSame(RfqStatus::SUBMITTED, $rfq->status);

        $order = Order::factory()->create();
        $this->assertSame(OrderStatus::PENDING_PAYMENT, $order->status);

        $bast = BastDocument::factory()->create(['order_id' => $order->id]);
        $invoice = Invoice::factory()->create([
            'order_id' => $order->id,
            'bast_id' => $bast->id,
        ]);
        $this->assertSame(InvoiceStatus::UNPAID, $invoice->status);
    }

    public function test_role_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();
        $this->assertSame(UserRole::BUYER_B2B, $user->role);

        $this->expectException(MassAssignmentException::class);
        User::create(['role' => UserRole::SUPERADMIN]);
    }

    public function test_order_total_recomputes_from_items(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['base_price' => 100000.00]);
        $order = Order::factory()->create(['user_id' => $user->id]);

        $order->refresh();
        $this->assertSame('0.00', $order->total_amount);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $order->refresh();
        $this->assertSame('300000.00', $order->total_amount);

        $item->delete();

        $order->refresh();
        $this->assertSame('0.00', $order->total_amount);
    }

    public function test_order_item_derives_price_from_product(): void
    {
        $product = Product::factory()->create(['base_price' => 100000.00]);
        $order = Order::factory()->create();

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);

        $this->assertSame('100000.00', $item->unit_price);
        $this->assertSame('300000.00', $item->subtotal);
    }

    public function test_invoice_bast_must_belong_to_same_order(): void
    {
        $orderA = Order::factory()->create();
        $orderB = Order::factory()->create();
        $bast = BastDocument::factory()->create(['order_id' => $orderB->id]);

        $this->expectException(QueryException::class);

        Invoice::factory()->create([
            'order_id' => $orderA->id,
            'bast_id' => $bast->id,
        ]);
    }
}
