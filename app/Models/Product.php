<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sku',
        'title',
        'slug',
        'description',
        'base_price',
        'margin_percentage',
        'tax_rate_percentage',
        'estimated_shipping',
        'tkdn_percentage',
        'is_sni',
        'warranty_info',
        'datasheet_url',
        'stock',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'margin_percentage' => 'decimal:2',
            'tax_rate_percentage' => 'decimal:2',
            'estimated_shipping' => 'decimal:2',
            'tkdn_percentage' => 'decimal:2',
            'is_sni' => 'boolean',
            'stock' => 'integer',
        ];
    }

    public function rfqItems(): HasMany
    {
        return $this->hasMany(RfqItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
