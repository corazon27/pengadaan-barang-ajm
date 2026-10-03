<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Phase 3C.6 — public product detail.
 *
 * The SKU is the public identifier (`/produk/{sku}`, named `produk.show`).
 *
 * SEO coverage here is deliberately limited to the server-rendered shell, which is
 * what the Blade layout owns (robots, canonical, og:url). There is intentionally NO
 * assertion that Inertia <Head> title/description/og:title/og:description appear in
 * the initial raw HTML: under the current no-SSR architecture those are client-side
 * Head behavior, and the SEO surface is not crawler-complete. This is the inherited,
 * accepted F2 limitation — no SSR, no head-management architecture, no Blade
 * redesign, no SEO framework, no scope expansion.
 */
class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ access

    public function test_unauthenticated_visitor_can_access_public_product_detail(): void
    {
        $product = Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $this->get('/produk/LAP-TKDN-001')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ProductDetail')
                ->where('product.sku', $product->sku));
    }

    public function test_detail_is_sourced_from_the_database(): void
    {
        Product::factory()->create(['sku' => 'SRV-0002']);

        $this->get('/produk/SRV-0002')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ProductDetail')
                ->where('product.title', Product::where('sku', 'SRV-0002')->first()->title));
    }

    // -------------------------------------------------------------- projection

    // C5/C6 — exact allowlist: the serialized keys MUST equal the approved
    // six-key public projection. Adding or renaming a field fails here.
    public function test_serialized_product_keys_equal_exact_public_allowlist(): void
    {
        Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $props = $this->get('/produk/LAP-TKDN-001')->viewData('page')['props'];

        $keys = array_keys($props['product']);
        sort($keys);

        $expected = ['available', 'description', 'price', 'sku', 'title', 'warrantyInfo'];
        sort($expected);

        $this->assertSame(
            $expected,
            $keys,
            'Serialized product keys must equal the approved 3C.6 public projection.'
        );
    }

    public function test_product_payload_shape_matches_public_contract(): void
    {
        Product::factory()->create([
            'sku' => 'LAP-TKDN-001',
            'title' => 'Laptop ThinkPad X1',
            'description' => 'Deskripsi publik',
            'base_price' => 12500000.00,
            'warranty_info' => '2 Tahun',
            'stock' => 5,
        ]);

        $this->get('/produk/LAP-TKDN-001')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ProductDetail')
                ->has('product', fn (AssertableInertia $item) => $item
                    ->where('sku', 'LAP-TKDN-001')
                    ->where('title', 'Laptop ThinkPad X1')
                    ->where('description', 'Deskripsi publik')
                    ->where('price', '12500000.00')
                    ->where('warrantyInfo', '2 Tahun')
                    ->where('available', true)));
    }

    public function test_price_is_serialized_as_a_string(): void
    {
        Product::factory()->create(['sku' => 'PR-001', 'base_price' => 1000.50]);

        $props = $this->get('/produk/PR-001')->viewData('page')['props'];

        $this->assertIsString($props['product']['price']);
        $this->assertSame('1000.50', $props['product']['price']);
    }

    // ----------------------------------------------------------- availability

    public function test_availability_is_derived_from_stock(): void
    {
        Product::factory()->create(['sku' => 'IN-001', 'stock' => 5]);
        Product::factory()->create(['sku' => 'OUT-001', 'stock' => 0]);

        $this->get('/produk/IN-001')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('product.available', true));

        $this->get('/produk/OUT-001')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('product.available', false));
    }

    // A zero-stock product is a page, not a missing product: 3C.6 introduces no
    // publication-state subsystem.
    public function test_zero_stock_product_returns_200_not_404(): void
    {
        Product::factory()->create(['sku' => 'OUT-002', 'stock' => 0]);

        $this->get('/produk/OUT-002')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('ProductDetail')
                ->where('product.available', false));
    }

    // ------------------------------------------------------- 404 / resolution

    public function test_unknown_sku_returns_404(): void
    {
        Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $this->get('/produk/DOES-NOT-EXIST')->assertNotFound();
    }

    public function test_empty_database_detail_returns_404(): void
    {
        $this->assertDatabaseCount('products', 0);

        $this->get('/produk/LAP-TKDN-001')->assertNotFound();
    }

    // The canonical persisted SKU round-trips.
    public function test_canonical_persisted_sku_returns_200(): void
    {
        Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $this->get('/produk/LAP-TKDN-001')->assertOk();
    }

    // LOCKED: exact, case-sensitive semantics. A non-canonical casing is not this
    // product — 404, never a redirect and never a case-insensitive match. This must
    // hold on SQLite AND MySQL (whose default collation is case-insensitive).
    public function test_non_canonical_casing_returns_404(): void
    {
        Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        foreach (['lap-tkdn-001', 'Lap-Tkdn-001', 'LAP-tkdn-001'] as $variant) {
            $this->get('/produk/'.$variant)
                ->assertNotFound("Non-canonical casing [{$variant}] must not resolve.");
        }
    }

    // Guards the enforcement itself: a genuinely lowercase PERSISTED SKU must
    // resolve at its own casing. This is not a test that lowercase succeeds for an
    // uppercase persisted SKU.
    public function test_genuinely_lowercase_persisted_sku_returns_200(): void
    {
        Product::factory()->create(['sku' => 'srv-0001']);

        $this->get('/produk/srv-0001')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('product.sku', 'srv-0001'));
    }

    // Inherited limitation, pinned: a SKU containing "/" cannot be addressed by a
    // single-segment route, so it is unreachable rather than mis-resolved.
    public function test_sku_containing_slash_is_unaddressable(): void
    {
        Product::factory()->create(['sku' => 'A/B']);

        $this->get('/produk/A%2FB')->assertNotFound();
    }

    // ------------------------------------------------------ payload integrity

    public function test_raw_stock_is_never_exposed(): void
    {
        Product::factory()->create(['sku' => 'STK-001', 'stock' => 7]);

        $response = $this->get('/produk/STK-001');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertArrayNotHasKey('stock', $props['product'], 'Raw stock must not be present in props.');

        $this->assertStringContainsString('available', $response->getContent());
    }

    public function test_internal_commercial_fields_are_never_exposed(): void
    {
        Product::factory()->create([
            'sku' => 'INT-001',
            'title' => 'Produk Uji Internal',
            'description' => 'Deskripsi uji.',
            'warranty_info' => 'Tidak ada',
            'margin_percentage' => 45.00,
            'tax_rate_percentage' => 11.00,
            'pph_rate_percentage' => 2.00,
            'estimated_shipping' => 99999.00,
        ]);

        $response = $this->get('/produk/INT-001');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        foreach (['margin_percentage', 'tax_rate_percentage', 'pph_rate_percentage', 'estimated_shipping', 'id'] as $key) {
            $this->assertArrayNotHasKey($key, $props['product'], "[{$key}] must not be serialized.");
        }

        $content = $response->getContent();
        foreach (['margin_percentage', 'tax_rate_percentage', 'pph_rate_percentage', 'estimated_shipping'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Detail page must not contain [{$marker}].");
        }
    }

    public function test_timestamps_are_never_exposed(): void
    {
        Product::factory()->create(['sku' => 'TS-001']);

        $props = $this->get('/produk/TS-001')->viewData('page')['props'];

        $this->assertArrayNotHasKey('created_at', $props['product']);
        $this->assertArrayNotHasKey('updated_at', $props['product']);
    }

    // C6 + §34 — slug is a 3C.5-excluded field and must not appear here either.
    public function test_slug_is_never_exposed(): void
    {
        Product::factory()->create([
            'sku' => 'SLG-001',
            'slug' => 'secret-slug-must-not-leak',
        ]);

        $response = $this->get('/produk/SLG-001');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertArrayNotHasKey('slug', $props['product']);
        $this->assertStringNotContainsString(
            'secret-slug-must-not-leak',
            $response->getContent(),
            'Persisted slug must not leak into the detail page.'
        );
    }

    public function test_datasheet_url_is_never_exposed(): void
    {
        Product::factory()->create([
            'sku' => 'DSH-001',
            'title' => 'Produk Uji Dokumen',
            'description' => 'Deskripsi uji.',
            'warranty_info' => 'Tidak ada',
            'datasheet_url' => 'https://sensitive.example.com/internal-datasheet.pdf',
        ]);

        $response = $this->get('/produk/DSH-001');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertArrayNotHasKey('datasheet_url', $props['product']);
        $this->assertStringNotContainsString('internal-datasheet.pdf', $response->getContent());
        $this->assertStringNotContainsString('datasheet', $response->getContent());
    }

    // TKDN/SNI are display-only presence flags in the 3C.5 catalog and are
    // deliberately absent from the detail surface (legal review / future
    // reintroduction). They are never even selected here.
    public function test_tkdn_and_sni_are_never_exposed(): void
    {
        Product::factory()->create([
            'sku' => 'CMP-001',
            'title' => 'Produk Uji Kepatuhan',
            'description' => 'Deskripsi uji.',
            'warranty_info' => 'Tidak ada',
            'tkdn_percentage' => 43.50,
            'is_sni' => true,
        ]);

        $response = $this->get('/produk/CMP-001');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertArrayNotHasKey('tkdn', $props['product']);
        $this->assertArrayNotHasKey('tkdn_percentage', $props['product']);
        $this->assertArrayNotHasKey('hasSni', $props['product']);
        $this->assertArrayNotHasKey('is_sni', $props['product']);

        $content = $response->getContent();
        foreach (['TKDN', 'tkdn', 'SNI', 'sni', 'is_sni', 'tkdn_percentage'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Detail page must not contain [{$marker}].");
        }
    }

    public function test_detail_never_leaks_compliance_or_financial_markers(): void
    {
        Product::factory()->create([
            'sku' => 'MARK-001',
            'title' => 'Produk Uji Penanda',
            'description' => 'Deskripsi uji.',
            'warranty_info' => 'Tidak ada',
        ]);

        $content = $this->get('/produk/MARK-001')->getContent();

        foreach (['nib', 'npwp', 'pkp', 'bank', 'consent', 'dsr'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Detail page must not contain [{$marker}].");
        }
    }

    // ------------------------------------------------------------- warranty

    public function test_warranty_info_is_passed_through_when_present(): void
    {
        Product::factory()->create(['sku' => 'WAR-001', 'warranty_info' => '2 Tahun Garansi Resmi']);

        $this->get('/produk/WAR-001')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('product.warrantyInfo', '2 Tahun Garansi Resmi'));
    }

    public function test_absent_warranty_payload_is_null_without_a_placeholder(): void
    {
        Product::factory()->create(['sku' => 'WAR-002', 'warranty_info' => null]);

        $this->get('/produk/WAR-002')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('product.warrantyInfo', null));
    }

    // ------------------------------------------- SEO guarantees (1-5) + F2 (6)

    // Guarantee 4 — canonical uses the resolved persisted SKU, never the model's
    // route key (which is the UUID primary key) and never the raw request segment.
    public function test_canonical_uses_persisted_sku_not_the_uuid_route_key(): void
    {
        $this->app->instance('env', 'production');
        config(['app.public_url' => 'https://public.example.com']);

        $product = Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $props = $this->get('/produk/LAP-TKDN-001')->viewData('page')['props'];

        $this->assertSame('https://public.example.com/produk/LAP-TKDN-001', $props['canonicalUrl']);
        $this->assertStringNotContainsString($product->id, (string) $props['canonicalUrl']);
    }

    // Guarantee 4, inverted: a genuinely lowercase persisted SKU must be reflected
    // verbatim — the canonical follows the database, not a normalized form.
    public function test_canonical_reflects_a_lowercase_persisted_sku_verbatim(): void
    {
        $this->app->instance('env', 'production');
        config(['app.public_url' => 'https://public.example.com']);

        Product::factory()->create(['sku' => 'srv-0001']);

        $props = $this->get('/produk/srv-0001')->viewData('page')['props'];

        $this->assertSame('https://public.example.com/produk/srv-0001', $props['canonicalUrl']);
    }

    // Guarantees 2 + 3 — exactly one canonical and exactly one matching og:url in
    // the production initial HTML shell.
    public function test_production_initial_html_emits_exactly_one_canonical_and_one_og_url(): void
    {
        $this->app->instance('env', 'production');
        config(['app.public_url' => 'https://public.example.com']);

        Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $html = $this->get('/produk/LAP-TKDN-001')->getContent();

        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertSame(1, substr_count($html, 'href="https://public.example.com/produk/LAP-TKDN-001"'));
        $this->assertSame(1, substr_count($html, 'property="og:url"'));
        $this->assertSame(1, substr_count($html, 'content="https://public.example.com/produk/LAP-TKDN-001"'));
    }

    // Guarantee 5 — non-production emits neither canonical nor og:url.
    public function test_canonical_and_og_url_are_absent_in_non_production(): void
    {
        config(['app.public_url' => 'https://public.example.com']);

        Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $html = $this->get('/produk/LAP-TKDN-001')->getContent();

        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('og:url', $html);
    }

    public function test_canonical_is_absent_in_production_without_a_configured_public_url(): void
    {
        $this->app->instance('env', 'production');
        config(['app.public_url' => null]);

        Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $html = $this->get('/produk/LAP-TKDN-001')->getContent();

        $this->assertStringNotContainsString('rel="canonical"', $html);
        $this->assertStringNotContainsString('og:url', $html);
    }

    // Guarantee 1 — product detail is an indexable surface; it is the indexable
    // value that 3C.5's discovery noindex policy deliberately routes toward.
    public function test_detail_is_not_marked_noindex(): void
    {
        Product::factory()->create(['sku' => 'LAP-TKDN-001']);

        $html = $this->get('/produk/LAP-TKDN-001')->getContent();

        $this->assertStringNotContainsString('noindex', $html);
    }

    // Guarantee 6 is an accepted F2 limitation and is deliberately NOT asserted:
    // there is no test that client-side Inertia navigation mutates the Blade-emitted
    // canonical/og:url, and no test asserts Inertia <Head> metadata is present in the
    // initial raw HTML. Documented, not verified.

    // ------------------------------------------------ catalog -> detail link
    //
    // SERVER CONTRACT ONLY. `/katalog` is a no-SSR Inertia page: the initial HTML
    // shell carries the serialized `data-page` JSON and an empty `<div id="app">`,
    // so the ProductCard `<a href="/produk/{sku}">` does not exist until Vue renders
    // it at runtime. Asserting the anchor in raw HTML is therefore invalid and would
    // require SSR. This test verifies only the server-side PREREQUISITE: the catalog
    // is reachable, the product is present, its public `sku` is available for the
    // client to build the href from, and the locked 3C.5 nine-key allowlist is
    // untouched by 3C.6.
    //
    // The rendered link itself (href value, single navigation target, keyboard
    // focusability, visible focus styling, activation -> /produk/{sku}, and
    // discovery-state preservation on back) is verified authoritatively in the
    // browser/E2E layer.
    public function test_catalog_payload_exposes_the_sku_needed_by_the_detail_link(): void
    {
        Product::factory()->create([
            'sku' => 'LAP-TKDN-001',
            'title' => 'Laptop ThinkPad X1',
            'stock' => 5,
        ]);

        $response = $this->get('/katalog');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertCount(1, $props['products'], 'Product must be present in the catalog payload.');
        $this->assertSame('LAP-TKDN-001', $props['products'][0]['sku'], 'Public sku must be available to build the detail href.');

        // The 3C.5 catalog payload must remain byte-identical after 3C.6: no slug,
        // no detailUrl, no additional field.
        $keys = array_keys($props['products'][0]);
        sort($keys);

        $expected = ['available', 'description', 'hasSni', 'id', 'price', 'sku', 'title', 'tkdn', 'warrantyInfo'];
        sort($expected);

        $this->assertSame($expected, $keys, 'The locked 3C.5 catalog allowlist must be unchanged by 3C.6.');
    }
}
