<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public product catalog.
 *
 * Thin presentation/query boundary only. It intentionally reuses the Product
 * model (single source of truth) and does NOT duplicate any business logic.
 * Only explicitly public columns are selected so internal/commercial fields
 * (margin, internal tax/shipping, raw stock, PPH, timestamps) are never even
 * loaded, let alone serialized to the browser.
 */
class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $paginator = Product::query()
            ->orderBy('id')
            ->select([
                'id',
                'sku',
                'title',
                'description',
                'base_price',
                'tkdn_percentage',
                'is_sni',
                'warranty_info',
                'stock',
            ])
            ->paginate(12);

        // Explicit whitelist. `stock` is consumed only to derive availability and
        // is then dropped — the raw count never reaches the client.
        $products = $paginator->map(fn (Product $product) => [
            'id' => $product->id,
            'sku' => $product->sku,
            'title' => $product->title,
            'description' => $product->description,
            'price' => (string) $product->base_price,
            'tkdn' => $product->tkdn_percentage !== null ? (string) $product->tkdn_percentage : null,
            'hasSni' => (bool) $product->is_sni,
            'warrantyInfo' => $product->warranty_info,
            'available' => (int) $product->stock > 0,
        ])->values();

        $canonicalUrl = app()->environment('production') && config('app.public_url')
            ? rtrim((string) config('app.public_url'), '/').'/katalog'
            : null;

        return Inertia::render('Catalog', [
            'products' => $products,
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'canonicalUrl' => $canonicalUrl,
        ]);
    }
}
