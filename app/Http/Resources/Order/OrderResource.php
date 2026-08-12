<?php

declare(strict_types=1);

namespace App\Http\Resources\Order;

use App\Http\Resources\Bast\BastResource;
use App\Http\Resources\Invoice\InvoiceResource;
use App\Http\Resources\Rfq\RfqResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'order_number' => $this->order_number,
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'rfq' => $this->whenLoaded('rfq', fn () => new RfqResource($this->rfq)),
            'items' => $this->whenLoaded('items', fn () => OrderItemResource::collection($this->items)),
            'bast_document' => $this->whenLoaded('bastDocument', fn () => new BastResource($this->bastDocument)),
            'invoices' => $this->whenLoaded('invoices', fn () => InvoiceResource::collection($this->invoices)),
            'status' => $this->status?->value,
            'status_label' => $this->status ? $this->statusLabel() : null,
            'total_amount' => $this->total_amount,
            'top_days' => $this->top_days,
            'po_document_url' => $this->po_document_url,
            'lkpp_product_url' => $this->lkpp_product_url,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
