<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $sku
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property decimal $base_price
 * @property decimal $margin_percentage
 * @property decimal $tax_rate_percentage
 * @property decimal $estimated_shipping
 * @property decimal $tkdn_percentage
 * @property bool $is_sni
 * @property string $warranty_info
 * @property string $datasheet_url
 * @property int $stock
 */
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

    protected $casts = [
        'base_price' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'tax_rate_percentage' => 'decimal:2',
        'estimated_shipping' => 'decimal:2',
        'tkdn_percentage' => 'decimal:2',
        'stock' => 'integer',
    ];
}
