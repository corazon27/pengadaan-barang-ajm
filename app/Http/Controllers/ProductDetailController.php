<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public product detail (Phase 3C.6).
 *
 * Thin presentation/query boundary, consistent with CatalogController. The SKU is
 * the public identifier: it is already part of the 3C.5 catalog allowlist, so the
 * catalog card links here with no payload change.
 *
 * Authorization is intentionally PUBLIC and LOCKED: `/katalog` is already a public
 * discovery surface, the projection below is non-sensitive, and this route carries
 * no auth middleware. `ProductPolicy::view(User $user, ...)` is therefore never
 * invoked — it is not guest-safe and no authorization framework is introduced here.
 *
 * Only explicitly public columns are selected. Margin, tax, PPH, shipping, slug,
 * datasheet_url, TKDN/SNI, and timestamps are never even loaded, so they cannot be
 * serialized. TKDN/SNI are display-only presence flags in the catalog and are
 * deliberately absent from the detail surface (legal review / future reintroduction).
 */
class ProductDetailController extends Controller
{
    public function show(string $sku): Response
    {
        $product = Product::query()
            ->select([
                'id',
                'sku',
                'title',
                'description',
                'base_price',
                'warranty_info',
                'stock',
            ])
            ->where('sku', $sku)
            ->first();

        // Exact, case-sensitive resolution — enforced portably.
        //
        // MySQL's default utf8mb4_unicode_ci collation would match 'lap-tkdn-001'
        // against the persisted 'LAP-TKDN-001', while SQLite's text comparison is
        // already case-sensitive. Comparing the hydrated persisted value against the
        // request segment is the single engine-agnostic enforcement point: no COLLATE,
        // no LOWER(), no functional index, no database-wide collation change, and no
        // redirect. A non-canonical casing is simply not this product.
        if ($product === null || $product->sku !== $sku) {
            abort(404);
        }

        // Canonical is derived from the RESOLVED PERSISTED SKU, never from the raw
        // request parameter. The route parameter is passed explicitly because
        // `route('produk.show', $product)` would resolve through Product's route key,
        // which is the UUID primary key — silently emitting /produk/<uuid>.
        // `stock` is consumed only to derive availability and is then dropped, so the
        // raw count never reaches the client.
        $canonicalUrl = app()->environment('production') && config('app.public_url')
            ? rtrim((string) config('app.public_url'), '/')
                .route('produk.show', ['sku' => $product->sku], absolute: false)
            : null;

        return Inertia::render('ProductDetail', [
            'product' => [
                'sku' => $product->sku,
                'title' => $product->title,
                'description' => $product->description,
                'price' => (string) $product->base_price,
                'warrantyInfo' => $product->warranty_info,
                'available' => (int) $product->stock > 0,
            ],
            'canonicalUrl' => $canonicalUrl,
        ]);
    }
}
