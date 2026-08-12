<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:200'],
            'npwp_number' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string'],
            'phone_number' => ['required', 'string', 'max:20'],
        ];
    }
}
