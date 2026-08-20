<?php

declare(strict_types=1);

namespace App\Http\Resources\Incident;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'incident_type' => $this->incident_type?->value,
            'severity' => $this->severity?->value,
            'status' => $this->status?->value,
            'breach_qualification_status' => $this->breach_qualification_status?->value,
            'title' => $this->title,
            'description' => $this->description,
            'affected_systems' => $this->affected_systems,
            'affected_data_categories' => $this->affected_data_categories,
            'number_of_subjects_known' => $this->number_of_subjects_known,
            'containment_status' => $this->containment_status,
            'evidence_snapshot' => $this->evidence_snapshot,
            'human_review_case_id' => $this->human_review_case_id,
            'breach_qualified_at' => $this->breach_qualified_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'actor_user_id' => $this->actor_user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
