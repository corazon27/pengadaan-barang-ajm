<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    // C5 — public catalog access
    public function test_unauthenticated_visitor_can_access_public_catalog(): void
    {
        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Catalog'));
    }

    // Source of truth
    public function test_catalog_products_are_sourced_from_the_database(): void
    {
        Product::factory()->create(['title' => 'Laptop ThinkPad X1', 'slug' => 'laptop-thinkpad-x1']);
        Product::factory()->count(2)->create();

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->has('products', 3)
                ->where('products.0.title', 'Laptop ThinkPad X1'));
    }

    // Pagination is server-side
    public function test_catalog_paginates_server_side(): void
    {
        Product::factory()->count(25)->create();

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->has('products', 12)
                ->where('pagination.currentPage', 1)
                ->where('pagination.lastPage', 3)
                ->where('pagination.total', 25));
    }

    // Empty state
    public function test_catalog_shows_empty_state_when_no_public_products(): void
    {
        $this->assertDatabaseCount('products', 0);

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->has('products', 0));
    }

    // The exact public payload shape the ProductCard consumes — no extras.
    public function test_product_payload_shape_matches_public_contract(): void
    {
        $product = Product::factory()->create([
            'sku' => 'SRV-001',
            'description' => 'Deskripsi publik',
            'base_price' => 12500000.00,
            'tkdn_percentage' => 43.50,
            'is_sni' => true,
            'warranty_info' => '2 Tahun',
            'stock' => 5,
        ]);

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->has('products.0', fn (AssertableInertia $item) => $item
                    ->where('id', $product->id)
                    ->where('sku', 'SRV-001')
                    ->where('title', $product->title)
                    ->where('description', 'Deskripsi publik')
                    ->where('price', '12500000.00')
                    ->where('tkdn', '43.50')
                    ->where('hasSni', true)
                    ->where('warrantyInfo', '2 Tahun')
                    ->where('available', true)));
    }

    // TKDN conditional
    public function test_tkdn_is_conditional(): void
    {
        Product::factory()->create(['tkdn_percentage' => 43.50]);
        Product::factory()->create(['tkdn_percentage' => null]);

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->where('products.0.tkdn', '43.50')
                ->where('products.1.tkdn', null));
    }

    // SNI marker conditional
    public function test_sni_marker_is_conditional(): void
    {
        Product::factory()->create(['is_sni' => true]);
        Product::factory()->create(['is_sni' => false]);

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->where('products.0.hasSni', true)
                ->where('products.1.hasSni', false));
    }

    // Availability derived from stock; raw stock never exposed
    public function test_availability_is_derived_and_raw_stock_is_never_exposed(): void
    {
        Product::factory()->create(['stock' => 5]);
        Product::factory()->create(['stock' => 0]);

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->where('products.0.available', true)
                ->where('products.1.available', false));
    }

    // C6 + §34 — serialized payload excludes internal fields and slug
    public function test_serialized_payload_excludes_internal_fields_and_slug(): void
    {
        Product::factory()->create([
            'sku' => 'SEC-001',
            'slug' => 'secret-slug-must-not-leak',
            'margin_percentage' => 45.00,
            'tax_rate_percentage' => 11.00,
            'pph_rate_percentage' => 2.00,
            'estimated_shipping' => 99999.00,
            'stock' => 7,
        ]);

        $response = $this->get('/katalog');
        $response->assertOk();

        $page = $response->viewData('page');
        $props = $page['props'];
        $this->assertIsArray($props);
        $this->assertArrayNotHasKey('stock', $props['products'][0], 'Raw stock must not be present in props.');
        $this->assertArrayNotHasKey('slug', $props['products'][0], 'slug is not required in 3C.4 and must not be sent.');
        $this->assertArrayNotHasKey('margin_percentage', $props['products'][0]);
        $this->assertArrayNotHasKey('tax_rate_percentage', $props['products'][0]);
        $this->assertArrayNotHasKey('estimated_shipping', $props['products'][0]);

        $content = $response->getContent();
        foreach (['margin_percentage', 'tax_rate_percentage', 'pph_rate_percentage', 'estimated_shipping', 'secret-slug-must-not-leak'] as $marker) {
            $this->assertStringNotContainsString(
                $marker,
                $content,
                "Serialized page must not contain [{$marker}]."
            );
        }
        $this->assertStringContainsString('available', $content, 'Derived availability signal must be present.');
    }

    // C5 — exact allowlist: serialized keys MUST equal the approved public projection.
    public function test_serialized_product_keys_equal_exact_public_allowlist(): void
    {
        Product::factory()->create();

        $props = $this->get('/katalog')->viewData('page')['props'];

        $keys = array_keys($props['products'][0]);
        sort($keys);

        $expected = ['available', 'description', 'hasSni', 'id', 'price', 'sku', 'title', 'tkdn', 'warrantyInfo'];
        sort($expected);

        $this->assertSame(
            $expected,
            $keys,
            'Serialized product keys must equal the approved public projection — adding or renaming a field fails here.'
        );
    }

    // C4 — a real public warranty value is passed through.
    public function test_warranty_info_is_passed_through_when_present(): void
    {
        Product::factory()->create(['warranty_info' => '2 Tahun Garansi Resmi']);

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->where('products.0.warrantyInfo', '2 Tahun Garansi Resmi'));
    }

    // C4 — absent warranty must not leak a placeholder; payload emits null.
    public function test_warranty_info_is_null_when_absent(): void
    {
        Product::factory()->create(['warranty_info' => null]);

        $this->get('/katalog')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Catalog')
                ->where('products.0.warrantyInfo', null));
    }

    // Catalog never sends compliance / financial / identity markers
    public function test_catalog_serialized_page_excludes_compliance_markers(): void
    {
        Product::factory()->create();

        $content = $this->get('/katalog')->getContent();

        foreach (['nib', 'npwp', 'pkp', 'bank', 'consent', 'dsr'] as $marker) {
            $this->assertStringNotContainsString($marker, $content, "Catalog page must not contain [{$marker}].");
        }
    }
}
