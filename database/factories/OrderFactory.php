<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'user_id' => User::factory(),
            'rfq_id' => null,
            'status' => OrderStatus::DRAFT,
            'top_days' => 30,
            'po_document_url' => null,
            'lkpp_product_url' => null,
        ];
    }
}
