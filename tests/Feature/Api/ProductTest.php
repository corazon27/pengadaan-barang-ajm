<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_fetch_products_with_filtering(): void
    {
        Product::factory()->create([
            'title' => 'Smartphone',
            'sku' => 'PHONE-001',
        ]);
        Product::factory()->count(2)->create();

        $response = $this->get('/api/v1/products?search=phone');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'errors',
            ])
            ->assertJsonPath('data.0.sku', 'PHONE-001')
            ->assertJsonCount(1, 'data');
    }

    public function test_search_matches_substring_in_title_or_sku(): void
    {
        Product::factory()->create([
            'title' => 'Wireless Bluetooth Speaker Pro',
            'sku' => 'AU-001',
        ]);
        Product::factory()->create([
            'title' => 'Kabel USB',
            'sku' => 'CBL-BLUETOOTH-9',
        ]);
        Product::factory()->count(2)->create();

        $response = $this->get('/api/v1/products?search=bluetooth');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_search_combines_with_other_filters(): void
    {
        $match = Product::factory()->create([
            'title' => 'Laptop Lenovo ThinkPad',
            'sku' => 'LAP-001',
            'is_sni' => true,
        ]);
        Product::factory()->create([
            'title' => 'Laptop Acer Aspire',
            'sku' => 'LAP-002',
            'is_sni' => false,
        ]);
        Product::factory()->create([
            'title' => 'Monitor Lenovo',
            'sku' => 'MON-001',
            'is_sni' => true,
        ]);

        $response = $this->get('/api/v1/products?search=laptop&is_sni=1');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    }

    public function test_listing_clamps_per_page_between_1_and_100(): void
    {
        Product::factory()->count(120)->create();

        $response = $this->get('/api/v1/products?per_page=500');

        $response->assertOk()
            ->assertJsonCount(100, 'data');

        $response = $this->get('/api/v1/products?per_page=0');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_public_can_filter_products_by_is_sni_and_tkdn(): void
    {
        $productSni = Product::factory()->create(['is_sni' => true]);
        $productNormal = Product::factory()->create(['is_sni' => false]);

        $response = $this->get('/api/v1/products?is_sni=1');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $productSni->id);

        $response = $this->get('/api/v1/products?min_tkdn=1');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $productSni->id);
    }

    public function test_public_can_view_single_product_by_id_or_slug(): void
    {
        $product = Product::factory()->create();

        $response = $this->get('/api/v1/products/'.$product->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $response = $this->get('/api/v1/products/'.$product->slug);

        $response->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_superadmin_can_create_product(): void
    {
        $user = User::factory()->superadmin()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/products', [
            'sku' => 'PROD-001',
            'title' => 'Test Product',
            'slug' => 'test-product',
            'description' => 'A test product',
            'base_price' => 100,
            'stock' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'sku',
                    'title',
                    'slug',
                ],
                'errors',
            ])
            ->assertJsonPath('data.sku', 'PROD-001');

        $this->assertDatabaseHas('products', ['sku' => 'PROD-001']);
    }

    public function test_non_admin_receives_403_on_product_creation(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/products', [
            'sku' => 'PROD-002',
            'title' => 'Another Product',
            'slug' => 'another-product',
            'description' => 'Another test',
            'base_price' => 200,
            'stock' => 5,
        ]);

        $response->assertForbidden();
    }

    public function test_superadmin_can_update_product(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->superadmin()->create();
        $this->actingAs($user);

        $response = $this->putJson('/api/v1/products/'.$product->id, [
            'sku' => $product->sku,
            'slug' => $product->slug,
            'title' => 'Updated Title',
            'base_price' => $product->base_price,
            'stock' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.stock', 20);
    }

    public function test_non_admin_receives_403_on_product_update(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->buyerB2b()->create();
        $this->actingAs($user);

        $response = $this->putJson('/api/v1/products/'.$product->id, [
            'sku' => $product->sku,
            'slug' => $product->slug,
            'title' => 'Should not update',
            'base_price' => $product->base_price,
            'stock' => $product->stock,
        ]);

        $response->assertStatus(403);
    }

    public function test_superadmin_can_delete_product(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->superadmin()->create();
        $this->actingAs($user);

        $response = $this->deleteJson('/api/v1/products/'.$product->id);

        $response->assertOk()
            ->assertJsonPath('message', 'Product berhasil dihapus');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_non_admin_receives_403_on_product_delete(): void
    {
        $product = Product::factory()->create();
        $user = User::factory()->buyerB2b()->create();
        $this->actingAs($user);

        $response = $this->deleteJson('/api/v1/products/'.$product->id);

        $response->assertStatus(403);
    }
}
