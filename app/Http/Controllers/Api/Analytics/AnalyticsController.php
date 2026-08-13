<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Rfq;
use App\Policies\AnalyticsPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Executive dashboard metrics for Superadmins.
     */
    public function dashboard(): JsonResponse
    {
        $this->authorize('view', AnalyticsPolicy::class);

        $rfqGroups = Rfq::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->status->value => (int) $row->total])
            ->toArray();

        $orderGroups = Order::query()
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as value')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->status->value => [
                    'count' => (int) $row->count,
                    'total_value' => (float) $row->value,
                ],
            ])
            ->toArray();

        $orderTotalValue = (float) Order::query()->sum('total_amount');
        $orderTotalCount = (int) Order::query()->count();

        $receivablesGroups = Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::UNPAID,
                InvoiceStatus::PARTIALLY_PAID,
                InvoiceStatus::OVERDUE,
            ])
            ->selectRaw('status, COUNT(*) as count, COALESCE(SUM(amount_due), 0) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->status->value => [
                    'count' => (int) $row->count,
                    'total' => (float) $row->total,
                ],
            ])
            ->toArray();

        $receivablesTotal = (float) Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::UNPAID,
                InvoiceStatus::PARTIALLY_PAID,
                InvoiceStatus::OVERDUE,
            ])
            ->sum('amount_due');

        $verifiedAmount = (float) Payment::query()
            ->where('status', PaymentStatus::VERIFIED)
            ->sum('amount');

        $verifiedCount = (int) Payment::query()
            ->where('status', PaymentStatus::VERIFIED)
            ->count();

        $tkdnAverage = DB::table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('SUM(products.tkdn_percentage * order_items.quantity) * 1.0 / NULLIF(SUM(order_items.quantity), 0) as avg')
            ->value('avg');

        return response()->json([
            'success' => true,
            'message' => 'Analytics dashboard retrieved',
            'data' => [
                'rfqs' => [
                    'by_status' => $rfqGroups,
                    'total' => array_sum($rfqGroups),
                ],
                'orders' => [
                    'by_status' => $orderGroups,
                    'total_count' => $orderTotalCount,
                    'total_value' => $orderTotalValue,
                ],
                'outstanding_receivables' => [
                    'total' => $receivablesTotal,
                    'by_status' => $receivablesGroups,
                ],
                'verified_payments' => [
                    'total_amount' => $verifiedAmount,
                    'count' => $verifiedCount,
                ],
                'tkdn_compliance' => [
                    'average_tkdn_percentage' => $tkdnAverage === null ? null : (float) $tkdnAverage,
                ],
                'generated_at' => now()->toISOString(),
            ],
            'errors' => null,
        ], 200);
    }
}
