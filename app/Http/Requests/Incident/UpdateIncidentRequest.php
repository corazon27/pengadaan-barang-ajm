<?php

declare(strict_types=1);

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIncidentRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string'],
            'affected_systems' => ['nullable', 'array'],
            'affected_data_categories' => ['nullable', 'array'],
            'number_of_subjects_known' => ['nullable', 'integer', 'min:0'],
            'containment_status' => ['nullable', 'string', 'max:50'],
            'evidence_snapshot' => ['nullable', 'array'],
        ];
    }
}
