<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BuyerClassification;
use App\Enums\VatCollectorStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Values\CommercialTaxContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 50),
        ];
    }

    /**
     * Capture a frozen commercial tax context on the item at creation time.
     */
    public function withCommercialContext(?CommercialTaxContext $context = null): static
    {
        return $this->afterCreating(function (OrderItem $item) use ($context) {
            $item->freezeCommercialTaxContext(
                $context ?? new CommercialTaxContext(
                    unitPriceSnapshot: $item->unit_price ?? '0',
                    lineBaseAmountSnapshot: bcadd(bcmul($item->unit_price ?? '0', (string) $item->quantity, 2), '0', 2),
                    buyerClassification: BuyerClassification::REGULAR,
                    collectorStatus: VatCollectorStatus::NOT_APPLICABLE,
                    transactionType: 'PENYERAHAN_BKP_JKP',
                    taxpayerStatus: 'PKP',
                ),
            );
        });
    }
}
