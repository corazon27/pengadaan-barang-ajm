<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\StoreRfqRequest;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\User;
use App\Services\RfqSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyer self-service RFQ surfaces.
 *
 * Read paths (`index`/`show`) are restricted with the ownership-aware
 * `visibleTo` scope AND the policies. The write path (`create`/`store`) is gated
 * by the narrower `RfqPolicy::createAsBuyer` ability so SUPERADMIN accounts
 * cannot raise buyer requests from the browser. The product selector exposes an
 * explicit public projection - raw stock, margin and tax fields are never loaded.
 */
class RfqController extends Controller
{
    private const SELECTOR_LIMIT = 200;

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
            'status_label' => $rfq->status->statusLabel(),
            'items_count' => (int) $rfq->items_count,
            'valid_until' => $rfq->valid_until?->toDateString(),
            'created_at' => $rfq->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Account/Rfq/Index', [
            'rfqs' => $rfqs,
            'pagination' => $this->pagination($paginator, '/akun/rfq'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('createAsBuyer', Rfq::class);

        $products = Product::query()
            ->orderBy('title')
            ->limit(self::SELECTOR_LIMIT)
            ->get(['id', 'sku', 'title', 'base_price', 'stock'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'sku' => $product->sku,
                'title' => $product->title,
                'price' => (string) $product->base_price,
                'available' => (int) $product->stock > 0,
            ])->values();

        return Inertia::render('Account/Rfq/Create', [
            'products' => $products,
        ]);
    }

    public function store(StoreRfqRequest $request, RfqSubmissionService $service): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rfq = $service->submit($user, $request->validated());

        return redirect()
            ->route('account.rfqs.show', $rfq)
            ->with('status', 'RFQ berhasil dibuat dan menunggu penawaran harga.');
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
                'status_label' => $rfq->status->statusLabel(),
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
}
