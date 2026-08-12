<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $title = fake()->words(3, true);

        return [
            'sku' => 'PRD-'.strtoupper(Str::random(8)),
            'title' => ucfirst($title),
            'slug' => Str::slug($title).'-'.Str::random(5),
            'description' => fake()->paragraph(),
            'base_price' => fake()->numberBetween(500000, 15000000),
            'margin_percentage' => 10.00,
            'tax_rate_percentage' => 11.00,
            'estimated_shipping' => 50000,
            'tkdn_percentage' => fake()->randomFloat(2, 25, 60),
            'is_sni' => fake()->boolean(80),
            'warranty_info' => '1 Tahun Garansi Resmi',
            'datasheet_url' => 'https://example.com/datasheets/sample.pdf',
            'stock' => fake()->numberBetween(10, 200),
        ];
    }
}
