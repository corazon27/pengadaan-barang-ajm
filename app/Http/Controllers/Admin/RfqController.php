<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Models\Rfq;
use App\Policies\AdminPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SUPERADMIN read-only RFQ queue. The console gate ({@see AdminPolicy}) is
 * checked explicitly; the listing is unscoped for SUPERADMIN (matching the
 * policy) and the detail view additionally re-authorizes the specific record
 * through RfqPolicy@view. No mutations.
 */
class RfqController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('view', AdminPolicy::class);

        $status = $this->validatedStatus($request);

        $paginator = Rfq::query()
            ->with('user:id,company_name')
            ->withCount('items')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $rfqs = $paginator->map(fn (Rfq $rfq): array => [
            'id' => $rfq->getKey(),
            'rfq_number' => $rfq->rfq_number,
            'buyer_company' => $rfq->user?->company_name,
            'status' => $rfq->status->value,
            'status_label' => $rfq->status->statusLabel(),
            'items_count' => (int) $rfq->items_count,
            'valid_until' => $rfq->valid_until?->toDateString(),
            'created_at' => $rfq->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Admin/Rfq/Index', [
            'rfqs' => $rfqs,
            'pagination' => $this->pagination($paginator, '/admin/rfq'),
            'filters' => ['status' => $status?->value],
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function show(Rfq $rfq): Response
    {
        $this->authorize('view', AdminPolicy::class);
        $this->authorize('view', $rfq);

        $rfq->load(['items.product', 'user:id,company_name,full_name']);

        return Inertia::render('Admin/Rfq/Show', [
            'rfq' => [
                'id' => $rfq->getKey(),
                'rfq_number' => $rfq->rfq_number,
                'buyer_company' => $rfq->user?->company_name,
                'buyer_name' => $rfq->user?->full_name,
                'status' => $rfq->status->value,
                'status_label' => $rfq->status->statusLabel(),
                'notes' => $rfq->notes,
                'admin_notes' => $rfq->admin_notes,
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
     * Resolve the status filter against the enum allowlist. An unknown value is
     * ignored rather than trusted into the query.
     */
    private function validatedStatus(Request $request): ?RfqStatus
    {
        return RfqStatus::tryFrom((string) $request->input('status'));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (RfqStatus $status): array => ['value' => $status->value, 'label' => $status->statusLabel()],
            RfqStatus::cases(),
        );
    }
}
