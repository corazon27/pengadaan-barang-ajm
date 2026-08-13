<?php

declare(strict_types=1);

namespace App\Http\Resources\Payment;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
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
            'invoice_id' => $this->invoice_id,
            'user_id' => $this->user_id,
            'amount' => $this->amount,
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method ? $this->paymentMethodLabel() : null,
            'payment_date' => $this->payment_date?->toISOString(),
            'proof_file_url' => $this->proof_file_url,
            'notes' => $this->notes,
            'status' => $this->status?->value,
            'status_label' => $this->status ? $this->statusLabel() : null,
            'verified_by' => $this->whenLoaded('verifiedBy', fn () => new UserResource($this->verifiedBy)),
            'verified_at' => $this->verified_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
