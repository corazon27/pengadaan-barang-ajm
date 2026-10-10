<?php

declare(strict_types=1);

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'title' => $this->title,
            'description' => $this->description,
            'price' => (string) $this->base_price,
            'warranty_info' => $this->warranty_info,
            'available' => $this->stock > 0,
        ];
    }
}
