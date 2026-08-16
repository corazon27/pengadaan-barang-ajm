<?php

declare(strict_types=1);

namespace App\Http\Requests\Pse;

use App\Enums\PseRegistrationApplicability;
use App\Enums\PseRegistrationStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePseRegistrationRequest extends FormRequest
{
    /**
     * PSE registries are governance records managed by SUPERADMIN.
     */
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
            'pse_type' => ['required', 'string', 'max:50'],
            'pse_registration_number' => ['nullable', 'string', 'max:100'],
            'registered_at' => ['nullable', 'date'],
            'maintenance_due_at' => ['nullable', 'date', 'after_or_equal:registered_at'],
            'registration_status' => ['nullable', Rule::enum(PseRegistrationStatus::class)],
            'applicability' => ['nullable', Rule::enum(PseRegistrationApplicability::class)],
        ];
    }
}
