<?php

declare(strict_types=1);

namespace App\Http\Requests\ProcessorAgreement;

use Illuminate\Foundation\Http\FormRequest;

class ChangeAgreementStatusRequest extends FormRequest
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
            'agreement_status' => ['required', 'string', 'in:DRAFT,ACTIVE,SUSPENDED,EXPIRED,TERMINATED'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
