<?php

declare(strict_types=1);

namespace App\Http\Requests\LegalFunction;

use Illuminate\Foundation\Http\FormRequest;

class OpenApplicabilityReviewRequest extends FormRequest
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
            'rule_id' => ['required', 'string', 'max:255'],
        ];
    }
}
