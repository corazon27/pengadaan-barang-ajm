<?php

declare(strict_types=1);

namespace App\Http\Requests\LegalFunction;

use Illuminate\Foundation\Http\FormRequest;

class StoreLegalFunctionRequest extends FormRequest
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
            'function_code' => ['required', 'string', 'in:DPO'],
            'function_category' => ['required', 'string', 'max:255'],
            'statutory_basis' => ['nullable', 'string', 'max:255'],
            'appointment_basis' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'scope' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ];
    }
}
