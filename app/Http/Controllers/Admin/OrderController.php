<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Policies\AdminPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SUPERADMIN read-only order queue. Console gate via {@see AdminPolicy}; detail
 * re-authorizes the record through OrderPolicy@view. Private document URLs
 * (po_document_url, lkpp_product_url) are deliberately never serialized.
 */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view', AdminPolicy::class);

        $status = OrderStatus::tryFrom((string) $request->input('status'));

        $paginator = Order::query()
            ->with('user:id,company_name')
            ->withCount('items')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $orders = $paginator->map(fn (Order $order): array => [
            'id' => $order->getKey(),
            'order_number' => $order->order_number,
            'buyer_company' => $order->user?->company_name,
            'status' => $order->status->value,
            'status_label' => $order->statusLabel(),
            'items_count' => (int) $order->items_count,
            'total_amount' => (string) $order->total_amount,
            'created_at' => $order->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'pagination' => $this->pagination($paginator, '/admin/pesanan'),
            'filters' => ['status' => $status?->value],
            'statuses' => array_map(
                fn (OrderStatus $case): array => ['value' => $case->value, 'label' => $case->statusLabel()],
                OrderStatus::cases(),
            ),
        ]);
    }

    public function show(Order $order): Response
    {
        $this->authorize('view', AdminPolicy::class);
        $this->authorize('view', $order);

        $order->load(['items', 'bastDocument', 'invoices.payments', 'user:id,company_name,full_name']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                'id' => $order->getKey(),
                'order_number' => $order->order_number,
                'buyer_company' => $order->user?->company_name,
                'buyer_name' => $order->user?->full_name,
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
