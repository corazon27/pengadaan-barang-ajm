<?php

declare(strict_types=1);

namespace App\Http\Resources\Pdp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DataSubjectRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject_user_id' => $this->subject_user_id,
            'subject_type' => $this->subject_type?->value,
            'right_code' => $this->right_code,
            'channel' => $this->channel?->value,
            'request_input' => $this->request_input,
            'identity_verification_status' => $this->identity_verification_status?->value,
            'identity_confidence' => $this->identity_confidence,
            'identity_verification_meta' => $this->identity_verification_meta,
            'processing_lawful_basis_evaluated' => $this->processing_lawful_basis_evaluated,
            'status' => $this->status?->value,
            'applicability_status' => $this->applicability_status,
            'handled_by' => $this->handled_by,
            'human_review_case_id' => $this->human_review_case_id,
            'decision_notes' => $this->decision_notes,
            'internal_sla_target_at' => $this->internal_sla_target_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
