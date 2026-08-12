<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoice;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'order_id' => $this->order_id,
            'bast_id' => $this->bast_id,
            'faktur_pajak_number' => $this->faktur_pajak_number,
            'invoice_pdf_url' => $this->invoice_pdf_url,
            'faktur_pajak_url' => $this->faktur_pajak_url,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'grand_total' => $this->grand_total,
            'amount_due' => $this->amount_due,
            'payment_status' => $this->status?->value,
            'issued_date' => $this->issued_date?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
