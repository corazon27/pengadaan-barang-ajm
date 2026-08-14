<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'product_sku_snapshot',
        'product_title_snapshot',
        'ppn_rate_snapshot',
        'pph_rate_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'ppn_rate_snapshot' => 'decimal:2',
            'pph_rate_snapshot' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item) {
            $attributes = $item->getAttributes();

            if (! array_key_exists('unit_price', $attributes) || $attributes['unit_price'] === null) {
                $item->unit_price = (string) (Product::query()->whereKey($item->product_id)->value('base_price') ?? 0);
            }

            // Freeze the commercial identity if not explicitly provided (e.g. in
            // tests/factories). The controller always sets these at order time.
            if (empty($attributes['product_sku_snapshot']) || empty($attributes['product_title_snapshot'])) {
                $product = Product::query()->whereKey($item->product_id)->first(['sku', 'title', 'tax_rate_percentage', 'pph_rate_percentage']);

                if ($product) {
                    $item->product_sku_snapshot = $product->sku;
                    $item->product_title_snapshot = $product->title;
                }

                if ($item->getAttribute('ppn_rate_snapshot') === null) {
                    $item->ppn_rate_snapshot = $product?->tax_rate_percentage;
                }

                if ($item->getAttribute('pph_rate_snapshot') === null) {
                    $item->pph_rate_snapshot = $product?->pph_rate_percentage;
                }
            }

            $item->subtotal = bcmul((string) $item->unit_price, (string) $item->quantity, 2);
        });

        static::saved(function (OrderItem $item) {
            Order::query()->whereKey($item->order_id)->first()?->recalculateTotal();
        });

        static::deleted(function (OrderItem $item) {
            Order::query()->whereKey($item->order_id)->first()?->recalculateTotal();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
