<?php

declare(strict_types=1);

namespace App\Http\Requests\LegalFunction;

use Illuminate\Foundation\Http\FormRequest;

class ApplyApplicabilityReviewRequest extends FormRequest
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
            'human_review_case_id' => ['required', 'uuid', 'exists:human_review_cases,id'],
        ];
    }
}
