<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\B2gEvidenceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewB2gEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in([
                B2gEvidenceStatus::ACCEPTED->value,
                B2gEvidenceStatus::REJECTED->value,
            ])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
