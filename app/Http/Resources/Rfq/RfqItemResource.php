<?php

declare(strict_types=1);

namespace App\Http\Resources\Rfq;

use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqItemResource extends JsonResource
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
            'rfq_id' => $this->rfq_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'quantity' => $this->quantity,
            'target_price' => $this->target_price,
            'offered_price' => $this->negotiated_price,
            'notes' => $this->notes,
            'subtotal_target' => $this->target_price ? $this->target_price * $this->quantity : null,
            'subtotal_offered' => $this->negotiated_price ? $this->negotiated_price * $this->quantity : null,
        ];
    }
}
