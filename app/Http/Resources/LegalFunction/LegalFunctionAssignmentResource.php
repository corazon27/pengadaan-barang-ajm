<?php

declare(strict_types=1);

namespace App\Http\Resources\LegalFunction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalFunctionAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'function_code' => $this->function_code,
            'function_category' => $this->function_category,
            'statutory_basis' => $this->statutory_basis,
            'appointment_basis' => $this->appointment_basis,
            'applicability_status' => $this->applicability_status?->value,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'scope' => $this->scope,
            'status' => $this->status?->value,
            'human_review_case_id' => $this->human_review_case_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
