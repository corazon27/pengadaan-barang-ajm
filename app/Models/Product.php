<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 * @property decimal $pph_rate_percentage
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
        'pph_rate_percentage',
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
        'pph_rate_percentage' => 'decimal:2',
        'estimated_shipping' => 'decimal:2',
        'tkdn_percentage' => 'decimal:2',
        'stock' => 'integer',
    ];

    /**
     * Search the public discovery fields (title or sku, case-insensitive).
     * Description is intentionally not searched.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('title', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    /**
     * Restrict to products marked as having SNI.
     */
    public function scopeSni(Builder $query): Builder
    {
        return $query->where('is_sni', true);
    }

    /**
     * Restrict to products with TKDN percentage at or above the given minimum.
     */
    public function scopeTkdnMin(Builder $query, float $min): Builder
    {
        return $query->where('tkdn_percentage', '>=', $min);
    }

    /**
     * Restrict to products currently available (raw stock above zero).
     * Raw stock itself is never serialized by the catalog projection.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('stock', '>', 0);
    }
}
