<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Models\Rfq;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer-facing, read-only RFQ views.
 *
 * The listing is restricted with the ownership-aware `visibleTo` scope AND the
 * `viewAny` policy gate; the detail view is authorized through `RfqPolicy@view`
 * before any data is loaded.
 */
class RfqController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Rfq::class);

        $paginator = Rfq::query()
            ->visibleTo($request->user())
            ->withCount('items')
            ->latest()
            ->paginate(15);

        $rfqs = $paginator->map(fn (Rfq $rfq): array => [
            'id' => $rfq->getKey(),
            'rfq_number' => $rfq->rfq_number,
            'status' => $rfq->status->value,
            'status_label' => $this->statusLabel($rfq->status),
            'items_count' => (int) $rfq->items_count,
            'valid_until' => $rfq->valid_until?->toDateString(),
            'created_at' => $rfq->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Account/Rfq/Index', [
            'rfqs' => $rfqs,
            'pagination' => $this->pagination($paginator, '/akun/rfq'),
        ]);
    }

    public function show(Request $request, Rfq $rfq): Response
    {
        $this->authorize('view', $rfq);

        $rfq->load(['items.product']);

        return Inertia::render('Account/Rfq/Show', [
            'rfq' => [
                'id' => $rfq->getKey(),
                'rfq_number' => $rfq->rfq_number,
                'status' => $rfq->status->value,
                'status_label' => $this->statusLabel($rfq->status),
                'notes' => $rfq->notes,
                'valid_until' => $rfq->valid_until?->toDateString(),
                'created_at' => $rfq->created_at?->toIso8601String(),
                'items' => $rfq->items->map(fn ($item): array => [
                    'id' => $item->getKey(),
                    'product_sku' => $item->product?->sku,
                    'product_title' => $item->product?->title,
                    'quantity' => (int) $item->quantity,
                    'target_price' => $item->target_price === null ? null : (string) $item->target_price,
                    'negotiated_price' => $item->negotiated_price === null ? null : (string) $item->negotiated_price,
                    'notes' => $item->notes,
                ])->values(),
            ],
        ]);
    }

    /**
     * Internal tracking labels. These describe AJM's own workflow state and are
     * explicitly NOT an official INAPROC/SPSE approval status (GOV-04).
     */
    private function statusLabel(RfqStatus $status): string
    {
        return match ($status) {
            RfqStatus::SUBMITTED => 'Diajukan',
            RfqStatus::REVIEWED => 'Ditinjau',
            RfqStatus::QUOTED => 'Penawaran Tersedia',
            RfqStatus::APPROVED => 'Disetujui',
            RfqStatus::REJECTED => 'Ditolak',
            RfqStatus::CONVERTED_TO_ORDER => 'Menjadi Pesanan',
            RfqStatus::CANCELLED => 'Dibatalkan',
        };
    }
}
