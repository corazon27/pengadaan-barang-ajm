<?php

declare(strict_types=1);

namespace App\Http\Requests\LegalFunction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLegalFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Excludes status, applicability_status (managed by dedicated flows).
     * function_code is allowed here for INACTIVE editing; service-level
     * guards enforce immutability for ACTIVE/SUSPENDED.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'function_code' => ['sometimes', 'string', 'in:DPO'],
            'function_category' => ['sometimes', 'string', 'max:255'],
            'statutory_basis' => ['sometimes', 'nullable', 'string', 'max:255'],
            'appointment_basis' => ['sometimes', 'nullable', 'string', 'max:255'],
            'effective_from' => ['sometimes', 'date'],
            'effective_until' => ['sometimes', 'nullable', 'date'],
            'scope' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
