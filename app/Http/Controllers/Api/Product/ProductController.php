<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a paginated listing of the products.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        // Apply filters
        if ($search = $request->input('search')) {
            $query->where('title', 'like', "%{$search}");
            $query->orWhere('sku', 'like', "%{$search}");
        }

        if ($isSni = $request->input('is_sni')) {
            $query->where('is_sni', $isSni);
        }

        if ($minTkdn = $request->input('min_tkdn')) {
            $query->where('tkdn_percentage', '>=', $minTkdn);
        }

        if ($inStock = $request->input('in_stock')) {
            $query->where('stock', '>', 0);
        }

        $perPage = $request->input('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Product listing retrieved',
            'data' => ProductResource::collection($products),
            'errors' => null,
        ], 200);
    }

    /**
     * Display a single product.
     */
    public function show($product): JsonResponse
    {
        $product = Product::find($product) ?? $this->findBySlug($product);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product tidak ditemukan.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved',
            'data' => new ProductResource($product),
            'errors' => null,
        ], 200);
    }

    /**
     * Create a new product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $product = Product::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product berhasil dibuat',
            'data' => new ProductResource($product),
            'errors' => null,
        ], 201);
    }

    /**
     * Update an existing product.
     */
    public function update(UpdateProductRequest $request, $product): JsonResponse
    {
        $product = Product::find($product) ?? $this->findBySlug($product);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product tidak ditemukan.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        $validated = $request->validated();

        $product->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Product berhasil diperbarui',
            'data' => new ProductResource($product),
            'errors' => null,
        ], 200);
    }

    /**
     * Delete a product.
     */
    public function destroy($product): JsonResponse
    {
        $product = Product::find($product) ?? $this->findBySlug($product);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product tidak ditemukan.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product berhasil dihapus',
            'data' => null,
            'errors' => null,
        ], 200);
    }

    /**
     * Find product by slug.
     */
    private function findBySlug(string $slug): ?Product
    {
        return Product::where('slug', $slug)->first();
    }
}
