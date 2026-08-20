<?php

declare(strict_types=1);

namespace App\Http\Requests\Pdp;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDataSubjectRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::SUPERADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:RECEIVED,IDENTITY_VERIFIED,PROCESSING,REVIEW_REQUIRED,FULFILLED,REJECTED,CLOSED'],
            'applicability_status' => ['sometimes', 'string', 'in:CONFIRMED,REVIEW_REQUIRED,UNRESOLVED,PENDING_LEGAL_REVIEW,APPLICABILITY_UNKNOWN'],
            'handled_by' => ['nullable', 'uuid', 'exists:users,id'],
            'decision_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
