<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CatalogDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- search

    public function test_search_matches_title_case_insensitively(): void
    {
        Product::factory()->create(['title' => 'Laptop ThinkPad X1']);
        Product::factory()->create(['title' => 'Printer Laser']);

        $this->get('/katalog?q=thinkpad')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->has('products', 1)
                ->where('products.0.title', 'Laptop ThinkPad X1')
                ->where('pagination.total', 1));
    }

    public function test_search_matches_sku(): void
    {
        Product::factory()->create(['sku' => 'SRV-2026-0001']);
        Product::factory()->create(['sku' => 'HWD-0002']);

        $this->get('/katalog?q=srv-2026')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 1)
                ->where('products.0.sku', 'SRV-2026-0001'));
    }

    public function test_search_composes_with_filters(): void
    {
        $hit = Product::factory()->create(['title' => 'Monitor 4K', 'is_sni' => true, 'stock' => 3]);
        Product::factory()->create(['title' => 'Monitor 4K', 'is_sni' => false]);
        Product::factory()->create(['title' => 'Printer', 'is_sni' => true]);

        $this->get('/katalog?q=monitor&sni=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 1)
                ->where('products.0.id', $hit->id));
    }

    public function test_search_with_no_match_returns_empty_and_zero_total(): void
    {
        Product::factory()->count(3)->create();

        $this->get('/katalog?q=nomatch')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 0)
                ->where('pagination.total', 0));
    }

    public function test_oversized_search_term_is_bounded_and_never_crashes(): void
    {
        Product::factory()->create(['title' => 'Perangkat Interoperable 2026']);

        $term = str_repeat('x', 5000);

        $this->get('/katalog?q='.$term)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 0));
    }

    public function test_control_characters_in_search_term_are_stripped(): void
    {
        Product::factory()->create(['title' => 'Kamera CCTV']);

        $this->get('/katalog?q='.urlencode("kam\x00era"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 1)
                ->where('products.0.title', 'Kamera CCTV'));
    }

    public function test_array_flooded_search_param_is_ignored(): void
    {
        Product::factory()->create(['title' => 'Laptop']);

        $this->get('/katalog?q[]=laptop&q[]=printer')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->has('products', 1));
    }

    // ---------------------------------------------------------------- filters

    public function test_sni_filter_returns_only_is_sni_products(): void
    {
        $withSni = Product::factory()->create(['is_sni' => true]);
        Product::factory()->create(['is_sni' => false]);

        $this->get('/katalog?sni=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 1)
                ->where('products.0.id', $withSni->id)
                ->where('products.0.hasSni', true));
    }

    public function test_tkdn_min_filter_returns_only_products_at_or_above_threshold(): void
    {
        $high = Product::factory()->create(['tkdn_percentage' => 55.00]);
        Product::factory()->create(['tkdn_percentage' => 20.00]);
        Product::factory()->create(['tkdn_percentage' => null]);

        $this->get('/katalog?tkdn_min=40')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 1)
                ->where('products.0.id', $high->id)
                ->where('products.0.tkdn', '55.00'));
    }

    public function test_tkdn_min_exactly_on_threshold_is_included(): void
    {
        $at = Product::factory()->create(['tkdn_percentage' => 40.00]);
        Product::factory()->create(['tkdn_percentage' => 39.99]);

        $this->get('/katalog?tkdn_min=40')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 1)
                ->where('products.0.id', $at->id));
    }

    public function test_tkdn_min_invalid_value_is_ignored(): void
    {
        Product::factory()->count(2)->create(['tkdn_percentage' => 15.00]);

        $this->get('/katalog?tkdn_min=abc')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 2));

        $this->get('/katalog?tkdn_min=-5')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products', 2));
    }

    public function test_tkdn_min_out_of_range_is_clamped_not_dropped(): void
    {
        Product::factory()->create(['tkdn_percentage' => 42.00]);

        $this->get('/katalog?tkdn_min=500')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 0));
    }

    public function test_availability_filter_returns_only_in_stock_products(): void
    {
        $inStock = Product::factory()->create(['stock' => 5]);
        Product::factory()->create(['stock' => 0]);

        $this->get('/katalog?availability=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 1)
                ->where('products.0.id', $inStock->id)
                ->where('products.0.available', true));
    }

    public function test_combined_filters_intersect(): void
    {
        $hit = Product::factory()->create(['is_sni' => true, 'tkdn_percentage' => 50.00, 'stock' => 2]);
        Product::factory()->create(['is_sni' => true, 'tkdn_percentage' => 10.00, 'stock' => 2]);
        Product::factory()->create(['is_sni' => true, 'tkdn_percentage' => 50.00, 'stock' => 0]);

        $this->get('/katalog?sni=1&tkdn_min=40&availability=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products', 1)
                ->where('products.0.id', $hit->id));
    }

    // ---------------------------------------------------------------- sort

    public function test_default_sort_is_insertion_order(): void
    {
        $first = Product::factory()->create(['base_price' => 900.00]);
        $second = Product::factory()->create(['base_price' => 100.00]);

        $this->get('/katalog')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $first->id)
                ->where('products.1.id', $second->id));
    }

    public function test_price_asc_sort_orders_by_base_price(): void
    {
        $cheap = Product::factory()->create(['base_price' => 100.00]);
        $mid = Product::factory()->create(['base_price' => 500.00]);
        $expensive = Product::factory()->create(['base_price' => 900.00]);

        $this->get('/katalog?sort=price_asc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $cheap->id)
                ->where('products.1.id', $mid->id)
                ->where('products.2.id', $expensive->id));
    }

    public function test_price_desc_sort_orders_by_base_price_descending(): void
    {
        $cheap = Product::factory()->create(['base_price' => 100.00]);
        $expensive = Product::factory()->create(['base_price' => 900.00]);

        $this->get('/katalog?sort=price_desc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $expensive->id)
                ->where('products.1.id', $cheap->id));
    }

    public function test_tkdn_desc_sort_orders_known_values_descending_with_null_last(): void
    {
        $high = Product::factory()->create(['tkdn_percentage' => 70.00]);
        $low = Product::factory()->create(['tkdn_percentage' => 10.00]);
        $null = Product::factory()->create(['tkdn_percentage' => null]);

        $this->get('/katalog?sort=tkdn_desc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $high->id)
                ->where('products.1.id', $low->id)
                ->where('products.2.id', $null->id));
    }

    public function test_tkdn_desc_sort_places_zero_before_null(): void
    {
        $zero = Product::factory()->create(['tkdn_percentage' => 0.00]);
        $null = Product::factory()->create(['tkdn_percentage' => null]);

        $this->get('/katalog?sort=tkdn_desc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $zero->id)
                ->where('products.1.id', $null->id));
    }

    public function test_newest_sort_orders_by_created_at_descending(): void
    {
        $older = Product::factory()->create(['created_at' => now()->subDays(5)]);
        $newer = Product::factory()->create(['created_at' => now()]);

        $this->get('/katalog?sort=newest')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $newer->id)
                ->where('products.1.id', $older->id));
    }

    public function test_ties_in_price_are_stablely_ordered_by_id(): void
    {
        $first = Product::factory()->create(['base_price' => 500.00]);
        $second = Product::factory()->create(['base_price' => 500.00]);

        $this->get('/katalog?sort=price_asc')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $first->id)
                ->where('products.1.id', $second->id));
    }

    public function test_invalid_sort_key_falls_back_to_default(): void
    {
        $first = Product::factory()->create(['base_price' => 900.00]);
        Product::factory()->create(['base_price' => 100.00]);

        $this->get('/katalog?sort=bestseller')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $first->id));
    }

    public function test_sql_injection_tokens_in_sort_are_rejected(): void
    {
        $first = Product::factory()->create(['base_price' => 900.00]);
        Product::factory()->create(['base_price' => 100.00]);

        $this->get('/katalog?sort=base_price),1);sleep(10)--')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('products.0.id', $first->id));
    }

    // ------------------------------------------------------ robustness / 500s

    public function test_garbage_query_never_returns_a_server_error(): void
    {
        Product::factory()->count(3)->create();

        $this->get('/katalog?q=;DROP TABLE products--&sni[]=1&tkdn_min=..&availability=999&sort=;')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Catalog'));

        $this->get('/katalog?page=0')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pagination.currentPage', 1));
    }

    // ----------------------------------------------- payload integrity (C5)

    public function test_filtered_response_still_equals_exact_public_allowlist(): void
    {
        Product::factory()->create(['title' => 'CCTV', 'is_sni' => true, 'stock' => 2]);

        $props = $this->get('/katalog?q=cctv&sni=1')->viewData('page')['props'];

        $keys = array_keys($props['products'][0]);
        sort($keys);

        $expected = ['available', 'description', 'hasSni', 'id', 'price', 'sku', 'title', 'tkdn', 'warrantyInfo'];
        sort($expected);

        $this->assertSame($expected, $keys);
    }

    public function test_discovery_response_never_leaks_internal_fields_or_slug(): void
    {
        Product::factory()->create([
            'title' => 'Aksesoris USB',
            'slug' => 'secret-slug-must-not-leak',
            'margin_percentage' => 45.00,
            'stock' => 7,
        ]);

        $response = $this->get('/katalog?q=usb');
        $response->assertOk();

        $props = $response->viewData('page')['props'];
        $this->assertArrayNotHasKey('stock', $props['products'][0]);
        $this->assertArrayNotHasKey('slug', $props['products'][0]);

        $content = $response->getContent();
        foreach (['margin_percentage', 'tax_rate_percentage', 'estimated_shipping', 'secret-slug-must-not-leak'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Discovery response must not contain [{$marker}].");
        }
    }

    // ------------------------------------------------------------- SEO flags

    public function test_noindex_flag_is_false_on_clean_catalog(): void
    {
        Product::factory()->count(3)->create();

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hasActiveDiscovery', false));
    }

    public function test_noindex_flag_is_true_when_any_discovery_param_active(): void
    {
        Product::factory()->count(3)->create();

        foreach (['/katalog?q=x', '/katalog?sni=1', '/katalog?tkdn_min=40', '/katalog?availability=1', '/katalog?sort=price_asc'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->where('hasActiveDiscovery', true));
        }
    }

    public function test_noindex_flag_is_true_on_paginated_deep_catalog(): void
    {
        Product::factory()->count(25)->create();

        $this->get('/katalog?page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('hasActiveDiscovery', true)
                ->where('pagination.currentPage', 2));
    }

    public function test_canonical_url_is_emitted_in_production_for_clean_and_discovery(): void
    {
        $this->app->instance('env', 'production');
        config(['app.public_url' => 'https://public.example.com']);

        Product::factory()->count(2)->create();

        $clean = $this->get('/katalog')->viewData('page')['props'];
        $this->assertSame('https://public.example.com/katalog', $clean['canonicalUrl']);

        $discovery = $this->get('/katalog?q=x')->viewData('page')['props'];
        $this->assertSame('https://public.example.com/katalog', $discovery['canonicalUrl']);
        $this->assertTrue($discovery['hasActiveDiscovery']);
    }

    public function test_pagination_base_url_carries_discovery_params_without_page(): void
    {
        Product::factory()->count(25)->create(['title' => 'Meja Kantor']);

        $this->get('/katalog?q=meja&sni=1&sort=price_asc&page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pagination.baseUrl', '/katalog?q=meja&sni=1&sort=price_asc')
                ->where('pagination.currentPage', 2));
    }

    public function test_pagination_base_url_is_clean_katalog_when_no_discovery(): void
    {
        Product::factory()->count(25)->create();

        $this->get('/katalog?page=2')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pagination.baseUrl', '/katalog'));
    }

    // ---------------------------------------------- initial HTML SEO delivery

    public function test_clean_catalog_initial_html_has_no_noindex(): void
    {
        Product::factory()->count(3)->create();

        $html = $this->get('/katalog')->getContent();

        $this->assertStringNotContainsString('noindex', $html);
    }

    public function test_discovery_urls_deliver_noindex_in_initial_html(): void
    {
        Product::factory()->count(25)->create();

        foreach (['/katalog?q=laptop', '/katalog?page=2', '/katalog?sort=price_asc'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertMatchesRegularExpression(
                '/<meta name="robots" content="noindex,follow">/',
                $html,
                "Initial HTML for [{$url}] must carry noindex,follow."
            );
        }
    }

    public function test_canonical_link_delivered_in_initial_html_only_in_production(): void
    {
        Product::factory()->count(3)->create();

        $dev = $this->get('/katalog')->getContent();
        $this->assertStringNotContainsString('rel="canonical"', $dev);
        $this->assertStringNotContainsString('og:url', $dev);

        $this->app->instance('env', 'production');
        config(['app.public_url' => 'https://public.example.com']);

        $clean = $this->get('/katalog')->getContent();
        $this->assertSame(1, substr_count($clean, 'rel="canonical"'));
        $this->assertSame(1, substr_count($clean, 'href="https://public.example.com/katalog"'));
        $this->assertSame(1, substr_count($clean, 'property="og:url"'));
        $this->assertSame(1, substr_count($clean, 'content="https://public.example.com/katalog"'));

        $discovery = $this->get('/katalog?q=x')->getContent();
        $this->assertSame(1, substr_count($discovery, 'rel="canonical"'));
        $this->assertSame(1, substr_count($discovery, 'property="og:url"'));
        $this->assertStringContainsString('noindex,follow', $discovery);
    }

    public function test_canonical_and_ogurl_absent_in_non_production_clean_and_discovery(): void
    {
        Product::factory()->count(3)->create();

        foreach (['/katalog', '/katalog?q=laptop', '/katalog?page=2', '/katalog?sort=price_asc'] as $url) {
            $html = $this->get($url)->getContent();

            $this->assertStringNotContainsString('rel="canonical"', $html, "Canonical must be absent in non-production for [{$url}].");
            $this->assertStringNotContainsString('og:url', $html, "og:url must be absent in non-production for [{$url}].");
        }
    }
}
