<?php

declare(strict_types=1);

namespace App\Http\Resources\Invoice;

use App\Enums\InvoiceStatus;
use App\Http\Resources\Payment\PaymentResource;
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
            'ppn_amount' => $this->ppn_amount,
            'pph_amount' => $this->pph_amount,
            'grand_total' => $this->grand_total,
            'amount_due' => $this->amount_due,
            'payment_term' => $this->payment_term?->value,
            'payment_term_label' => $this->payment_term ? $this->payment_term->statusLabel() : null,
            'payment_status' => $this->status?->value,
            'payment_status_label' => $this->status ? $this->status->statusLabel() : null,
            'tax_calculated' => $this->status !== InvoiceStatus::REVIEW_REQUIRED,
            'tax_calculation_version' => $this->tax_calculation_version,
            'faktur_code' => $this->when(
                $this->relationLoaded('ruleSnapshots'),
                fn () => $this->ruleSnapshots->pluck('faktur_code')->first(fn ($code) => $code !== null),
            ),
            'rule_snapshots' => $this->whenLoaded('ruleSnapshots', fn () => $this->ruleSnapshots->map(fn ($snapshot) => [
                'rule_code' => $snapshot->rule_code,
                'rule_version' => $snapshot->rule_version,
                'faktur_code' => $snapshot->faktur_code,
                'dpp_amount' => $snapshot->dpp_amount,
                'tax_amount' => $snapshot->pivot?->tax_amount,
            ])),
            'paid_amount' => $this->when($this->relationLoaded('payments'), fn () => $this->verifiedPaidAmount()),
            'issued_date' => $this->issued_date?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'payments' => $this->whenLoaded('payments', fn () => PaymentResource::collection($this->payments)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
