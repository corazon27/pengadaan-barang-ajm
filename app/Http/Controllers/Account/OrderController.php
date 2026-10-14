<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer-facing, read-only order views.
 *
 * The listing is restricted with the ownership-aware `visibleTo` scope AND the
 * `viewAny` policy gate; the detail view is authorized through `OrderPolicy@view`
 * before any data is loaded, so a cross-buyer id yields a 403 without leaking
 * whether the record exists.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Order::class);

        $paginator = Order::query()
            ->visibleTo($request->user())
            ->withCount('items')
            ->latest()
            ->paginate(15);

        $orders = $paginator->map(fn (Order $order): array => [
            'id' => $order->getKey(),
            'order_number' => $order->order_number,
            'status' => $order->status->value,
            'status_label' => $order->statusLabel(),
            'items_count' => (int) $order->items_count,
            'total_amount' => (string) $order->total_amount,
            'created_at' => $order->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Account/Orders/Index', [
            'orders' => $orders,
            'pagination' => $this->pagination($paginator, '/akun/pesanan'),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load(['items', 'bastDocument', 'invoices.payments']);

        return Inertia::render('Account/Orders/Show', [
            'order' => [
                'id' => $order->getKey(),
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->statusLabel(),
                'total_amount' => (string) $order->total_amount,
                'top_days' => $order->top_days,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item): array => [
                    'id' => $item->getKey(),
                    'product_sku' => $item->product_sku_snapshot,
                    'product_title' => $item->product_title_snapshot,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'subtotal' => (string) $item->subtotal,
                ])->values(),
                'bast' => $order->bastDocument === null ? null : [
                    'bast_number' => $order->bastDocument->bast_number,
                    'status' => $order->bastDocument->status->value,
                    'status_label' => $order->bastDocument->statusLabel(),
                    'signed_date' => $order->bastDocument->signed_date?->toDateString(),
                ],
                'invoices' => $order->invoices->map(fn ($invoice): array => [
                    'id' => $invoice->getKey(),
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status->value,
                    'status_label' => $invoice->status->statusLabel(),
                    'grand_total' => (string) $invoice->grand_total,
                    'amount_due' => (string) $invoice->amount_due,
                    'paid_amount' => $invoice->payments
                        ->where('status', PaymentStatus::VERIFIED)
                        ->reduce(
                            fn (string $carry, Payment $payment): string => bcadd($carry, (string) $payment->amount, 2),
                            '0.00',
                        ),
                    'due_date' => $invoice->due_date?->toDateString(),
                ])->values(),
            ],
        ]);
    }
}
