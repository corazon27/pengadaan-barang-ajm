<?php

declare(strict_types=1);

namespace App\Http\Resources\Order;

use App\Http\Resources\Product\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'order_id' => $this->order_id,
            'product_id' => $this->product_id,
            'product_sku_snapshot' => $this->product_sku_snapshot,
            'product_title_snapshot' => $this->product_title_snapshot,
            'ppn_rate_snapshot' => $this->ppn_rate_snapshot,
            'pph_rate_snapshot' => $this->pph_rate_snapshot,
            'unit_price_snapshot' => $this->unit_price_snapshot,
            'line_base_amount_snapshot' => $this->line_base_amount_snapshot,
            'product_classification_snapshot' => $this->product_classification_snapshot,
            'buyer_classification_snapshot' => $this->buyer_classification_snapshot,
            'collector_status_snapshot' => $this->collector_status_snapshot,
            'transaction_type_snapshot' => $this->transaction_type_snapshot,
            'taxpayer_status_snapshot' => $this->taxpayer_status_snapshot,
            'faktur_code' => $this->when(
                $this->relationLoaded('ruleSnapshots'),
                fn () => $this->ruleSnapshots->pluck('faktur_code')->first(fn ($code) => $code !== null),
            ),
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,
        ];
    }
}
