<?php

declare(strict_types=1);

namespace App\Http\Requests\ProcessorAgreement;

use Illuminate\Foundation\Http\FormRequest;

class DetermineRoleRequest extends FormRequest
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
            'processor_role_status' => ['required', 'string', 'in:PROCESSOR,NOT_PROCESSOR'],
        ];
    }
}
