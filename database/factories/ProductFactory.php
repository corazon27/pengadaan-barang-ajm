<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->numerify('PROD-########'),
            'title' => fake()->sentence(),
            'slug' => fake()->slug(),
            'description' => fake()->paragraph(),
            'base_price' => fake()->randomFloat(2, 10, 500),
            'margin_percentage' => fake()->randomFloat(0, 0, 50),
            'tax_rate_percentage' => fake()->randomFloat(0, 0, 25),
            'pph_rate_percentage' => null,
            'estimated_shipping' => fake()->randomFloat(2, 5, 30),
            'tkdn_percentage' => fake()->randomFloat(0, 0, 20),
            'is_sni' => fake()->boolean(0.3),
            'warranty_info' => fake()->sentence(),
            'datasheet_url' => fake()->url(),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}
