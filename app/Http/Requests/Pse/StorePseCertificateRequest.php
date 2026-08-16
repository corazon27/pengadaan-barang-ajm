<?php

declare(strict_types=1);

namespace App\Http\Requests\Pse;

use App\Enums\PseCertificateStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePseCertificateRequest extends FormRequest
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
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'psre_provider' => ['required', 'string', 'max:200'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'certificate_status' => ['nullable', Rule::enum(PseCertificateStatus::class)],
            'verification_status' => ['nullable', Rule::enum(VerificationStatus::class)],
        ];
    }
}
