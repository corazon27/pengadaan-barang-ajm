<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfqItem>
 */
class RfqItemFactory extends Factory
{
    protected $model = RfqItem::class;

    public function definition(): array
    {
        return [
            'rfq_id' => Rfq::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 100),
            'negotiated_price' => null,
        ];
    }
}
