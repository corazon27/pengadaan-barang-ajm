<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CatalogSort;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public product catalog.
 *
 * Thin presentation/query boundary only. It intentionally reuses the Product
 * model (single source of truth) and does NOT duplicate any business logic.
 * Discovery (search/filter/sort) is normalized from the URL query string and
 * applied through allowlisted model scopes + the CatalogSort enum — raw query
 * input is never interpolated into SQL. Only explicitly public columns are
 * selected so internal/commercial fields (margin, internal tax/shipping, raw
 * stock, PPH, timestamps) are never even loaded, let alone serialized.
 */
class CatalogController extends Controller
{
    public function index(Request $request): Response
    {
        $discovery = $this->normalizeDiscovery($request);

        $paginator = Product::query()
            ->when($discovery['q'] !== null, fn (Builder $query) => $query->search($discovery['q']))
            ->when($discovery['sni'], fn (Builder $query) => $query->sni())
            ->when($discovery['tkdn_min'] !== null, fn (Builder $query) => $query->tkdnMin($discovery['tkdn_min']))
            ->when($discovery['availability'], fn (Builder $query) => $query->available())
            ->tap(fn (Builder $query) => $discovery['sort']->apply($query))
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

        $hasActiveDiscovery = $discovery['q'] !== null
            || $discovery['sni']
            || $discovery['tkdn_min'] !== null
            || $discovery['availability']
            || $discovery['sort'] !== CatalogSort::DEFAULT
            || $paginator->currentPage() > 1;

        return Inertia::render('Catalog', [
            'products' => $products,
            // D1: the normalized server truth, echoed verbatim so the frontend
            // renders exactly what the server applied. `page` intentionally lives
            // only on `pagination` — it is never part of `discovery`.
            'discovery' => [
                'q' => $discovery['q'],
                'sni' => $discovery['sni'],
                'tkdn_min' => $discovery['tkdn_min'],
                'availability' => $discovery['availability'],
                'sort' => $discovery['sort']->value,
            ],
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'baseUrl' => $this->paginationBaseUrl($discovery),
            ],
            'canonicalUrl' => $canonicalUrl,
            'hasActiveDiscovery' => $hasActiveDiscovery,
        ]);
    }

    /**
     * Normalize the discovery query string into the single allowlisted shape.
     *
     * Invalid or unknown values are ignored (never error), so the catalog treats
     * the URL as the source of truth rather than fighting the client with 302s.
     *
     * @return array{q: string|null, sni: bool, tkdn_min: float|null, availability: bool, sort: CatalogSort}
     */
    private function normalizeDiscovery(Request $request): array
    {
        $q = $this->queryString($request, 'q');
        $q = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($q)) ?? '';
        $q = mb_substr($q, 0, 120);
        $q = $q === '' ? null : $q;

        $sni = $this->queryString($request, 'sni') === '1';

        $tkdnMin = $this->queryString($request, 'tkdn_min');
        $tkdnMin = is_numeric($tkdnMin) ? min(100.0, max(0.0, (float) $tkdnMin)) : null;

        $availability = $this->queryString($request, 'availability') === '1';

        $sort = CatalogSort::fromRequest($this->queryString($request, 'sort'));

        return [
            'q' => $q,
            'sni' => $sni,
            'tkdn_min' => $tkdnMin,
            'availability' => $availability,
            'sort' => $sort,
        ];
    }

    /**
     * Read a single-value query param; array-flooded params are treated as absent
     * rather than being coerced (the page must stay calm under hostile input).
     */
    private function queryString(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    /**
     * Rebuild the base catalog URL carrying the active discovery params (page
     * intentionally excluded — the pagination links append it themselves).
     */
    private function paginationBaseUrl(array $discovery): string
    {
        $params = [];

        if ($discovery['q'] !== null) {
            $params['q'] = $discovery['q'];
        }
        if ($discovery['sni']) {
            $params['sni'] = '1';
        }
        if ($discovery['tkdn_min'] !== null) {
            $params['tkdn_min'] = (string) $discovery['tkdn_min'];
        }
        if ($discovery['availability']) {
            $params['availability'] = '1';
        }
        if ($discovery['sort'] !== CatalogSort::DEFAULT) {
            $params['sort'] = $discovery['sort']->value;
        }

        return $params === [] ? '/katalog' : '/katalog?'.http_build_query($params);
    }
}
