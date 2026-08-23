<?php

declare(strict_types=1);

namespace App\Http\Resources\ProcessorAgreement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProcessorAgreementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'counterparty_name' => $this->counterparty_name,
            'counterparty_identifier' => $this->counterparty_identifier,
            'processor_role_status' => $this->processor_role_status->value,
            'agreement_status' => $this->agreement_status->value,
            'is_effective' => $this->isActiveAgreement(),
            'data_categories' => $this->data_categories,
            'processing_purposes' => $this->processing_purposes,
            'legal_basis' => $this->legal_basis,
            'evidence_reference' => $this->evidence_reference,
            'evidence_verified' => $this->evidence_verified,
            'evidence_verified_at' => $this->evidence_verified_at?->toIso8601String(),
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'human_review_case_id' => $this->human_review_case_id,
            'actor_user_id' => $this->actor_user_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
