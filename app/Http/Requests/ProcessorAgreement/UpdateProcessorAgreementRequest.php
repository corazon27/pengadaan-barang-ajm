<?php

declare(strict_types=1);

namespace App\Http\Requests\ProcessorAgreement;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcessorAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Excludes processor_role_status, agreement_status, evidence_verified
     * (managed by dedicated flows/services).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counterparty_name' => ['sometimes', 'string', 'max:255'],
            'counterparty_identifier' => ['sometimes', 'nullable', 'array'],
            'data_categories' => ['sometimes', 'nullable', 'array'],
            'processing_purposes' => ['sometimes', 'nullable', 'array'],
            'legal_basis' => ['sometimes', 'nullable', 'string', 'max:255'],
            'evidence_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'effective_from' => ['sometimes', 'nullable', 'date'],
            'effective_until' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
