<?php

declare(strict_types=1);

namespace App\Http\Requests\ProcessorAgreement;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcessorAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counterparty_name' => ['required', 'string', 'max:255'],
            'counterparty_identifier' => ['nullable', 'array'],
            'data_categories' => ['nullable', 'array'],
            'processing_purposes' => ['nullable', 'array'],
            'legal_basis' => ['nullable', 'string', 'max:255'],
            'evidence_reference' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
