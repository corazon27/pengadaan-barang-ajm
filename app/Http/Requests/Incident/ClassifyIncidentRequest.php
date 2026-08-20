<?php

declare(strict_types=1);

namespace App\Http\Requests\Incident;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassifyIncidentRequest extends FormRequest
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
            'incident_type' => ['required', Rule::enum(IncidentType::class)],
            'severity' => ['required', Rule::enum(IncidentSeverity::class)],
        ];
    }
}
