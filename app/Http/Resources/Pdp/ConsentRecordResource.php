<?php

declare(strict_types=1);

namespace App\Http\Resources\Pdp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsentRecordResource extends JsonResource
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
            'purpose' => $this->purpose,
            'processing_lawful_basis' => $this->processing_lawful_basis?->value,
            'notice_version' => $this->notice_version,
            'document_ref' => $this->document_ref,
            'consent_status' => $this->consent_status?->value,
            'granted_at' => $this->granted_at?->toIso8601String(),
            'withdrawn_at' => $this->withdrawn_at?->toIso8601String(),
            'withdrawal_deadline_at' => $this->withdrawal_deadline_at?->toIso8601String(),
            'source_channel' => $this->source_channel?->value,
            'actor_user_id' => $this->actor_user_id,
            'evidence_reference' => $this->evidence_reference,
            'rule_id' => $this->rule_id,
            'predecessor_consent_id' => $this->predecessor_consent_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
