<?php

declare(strict_types=1);

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'base_price' => $this->base_price,
            'margin_percentage' => $this->margin_percentage,
            'tax_rate_percentage' => $this->tax_rate_percentage,
            'estimated_shipping' => $this->estimated_shipping,
            'tkdn_percentage' => $this->tkdn_percentage,
            'is_sni' => $this->is_sni,
            'warranty_info' => $this->warranty_info,
            'datasheet_url' => $this->datasheet_url,
            'stock' => $this->stock,
        ];
    }
}
