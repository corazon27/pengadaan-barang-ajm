<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function public_can_fetch_products_with_filtering(): void
    {
        // Create some products
        Product::factory()->count(3)->create();

        // Test search filtering
        $response = $this->get('/api/v1/products?search=phone');
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'errors',
            ])
            ->assertJsonPath('data.*.sku');
    }

    /** @test */
    public function public_can_filter_products_by_is_sni_and_tkdn(): void
    {
        $productSni = Product::factory()->create(['is_sni' => true]);
        $productNormal = Product::factory()->create(['is_sni' => false]);

        $response = $this->get('/api/v1/products?is_sni=1');
        $response->assertOk()
            ->assertJsonPath('data.0.id', $productSni->id);
    }

    /** @test */
    public function public_can_view_single_product_by_id_or_slug(): void
    {
        $product = Product::factory()->first();

        $response = $this->get('/api/v1/products/'.$product->id);
        $response->assertOk()
            ->assertJsonPath('data.id', $product->id);

        $response = $this->get('/api/v1/products/'.$product->slug);
        $response->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    /** @test */
    public function superadmin_can_create_product(): void
    {
        $user = User::factory()->superadmin()->create();

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
                    // other fields
                ],
                'errors',
            ]);
    }

    /** @test */
    public function non_admin_receives_403_on_product_creation(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $response = $this->postJson('/api/v1/products', [
            'sku' => 'PROD-002',
            'title' => 'Another Product',
            'slug' => 'another-product',
            'description' => 'Another test',
            'base_price' => 200,
            'stock' => 5,
        ]);

        $response->assertForbidden(); // 403
    }

    /** @test */
    public function superadmin_can_update_product(): void
    {
        $product = Product::factory()->first();
        $user = User::factory()->superadmin()->create();
        $this->actingAs($user);

        $response = $this->putJson('/api/v1/products/'.$product->id, [
            'title' => 'Updated Title',
            'stock' => 20,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.stock', 20);
    }

    /** @test */
    public function non_admin_receives_403_on_product_update(): void
    {
        $product = Product::factory()->first();
        $user = User::factory()->buyerB2b()->create();

        $response = $this->putJson('/api/v1/products/'.$product->id, [
            'title' => 'Should not update',
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function superadmin_can_delete_product(): void
    {
        $product = Product::factory()->first();
        $user = User::factory()->superadmin()->create();
        $this->actingAs($user);

        $response = $this->deleteJson('/api/v1/products/'.$product->id);
        $response->assertOk()
            ->assertJsonPath('message', 'Product berhasil dihapus');

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /** @test */
    public function non_admin_receives_403_on_product_delete(): void
    {
        $product = Product::factory()->first();
        $user = User::factory()->buyerB2b()->create();

        $response = $this->deleteJson('/api/v1/products/'.$product->id);
        $response->assertStatus(403);
    }
}
