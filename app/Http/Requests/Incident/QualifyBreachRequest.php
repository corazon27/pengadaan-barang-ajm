<?php

declare(strict_types=1);

namespace App\Http\Requests\Incident;

use App\Enums\BreachQualificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QualifyBreachRequest extends FormRequest
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
            'breach_qualification_status' => ['required', Rule::enum(BreachQualificationStatus::class)],
            'reason' => ['nullable', 'string'],
        ];
    }
}
