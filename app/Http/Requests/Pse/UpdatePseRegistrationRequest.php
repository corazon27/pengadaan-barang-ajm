<?php

declare(strict_types=1);

namespace App\Http\Requests\Pse;

use App\Enums\PseRegistrationApplicability;
use App\Enums\PseRegistrationStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePseRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::SUPERADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'pse_type' => ['sometimes', 'string', 'max:50'],
            'pse_registration_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'registered_at' => ['sometimes', 'nullable', 'date'],
            'maintenance_due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:registered_at'],
            'registration_status' => ['sometimes', Rule::enum(PseRegistrationStatus::class)],
            'applicability' => ['sometimes', Rule::enum(PseRegistrationApplicability::class)],
        ];
    }
}
