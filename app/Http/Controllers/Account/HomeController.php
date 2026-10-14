<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Rfq;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer account home. Read-only summary surface: profile, bounded aggregates
 * and the five most recent orders. All listing data is scoped through the
 * ownership-aware `visibleTo` query scopes (never a company-name proxy) and the
 * counts are bounded aggregates rather than full-table fetches.
 */
class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $recentOrders = Order::query()
            ->visibleTo($user)
            ->latest()
            ->limit(5)
            ->get(['id', 'order_number', 'status', 'total_amount', 'created_at'])
            ->map(fn (Order $order): array => [
                'id' => $order->getKey(),
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'status_label' => $order->statusLabel(),
                'total_amount' => (string) $order->total_amount,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Account/Home', [
            'account' => [
                'full_name' => $user->full_name,
                'email' => $user->email,
                'company_name' => $user->company_name,
                'role' => $user->role->value,
                'status' => $user->status->value,
            ],
            'summary' => [
                'rfqs_open' => Rfq::query()
                    ->visibleTo($user)
                    ->whereIn('status', [
                        RfqStatus::SUBMITTED,
                        RfqStatus::REVIEWED,
                        RfqStatus::QUOTED,
                        RfqStatus::APPROVED,
                    ])
                    ->count(),
                'orders_active' => Order::query()
                    ->visibleTo($user)
                    ->whereIn('status', [
                        OrderStatus::PENDING_PAYMENT,
                        OrderStatus::PROCESSING,
                        OrderStatus::SHIPPED,
                        OrderStatus::DELIVERED,
                    ])
                    ->count(),
                'invoices_unpaid' => Invoice::query()
                    ->visibleTo($user)
                    ->whereIn('status', [
                        InvoiceStatus::UNPAID,
                        InvoiceStatus::PARTIALLY_PAID,
                        InvoiceStatus::OVERDUE,
                    ])
                    ->count(),
                'notifications_unread' => $user->unreadNotifications()->count(),
            ],
            'recentOrders' => $recentOrders,
        ]);
    }
}
