<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // Deliberate policy difference: public self-service registration
            // enforces a minimum of 8 characters, while privileged CLI
            // provisioning (ajm:create-superadmin) enforces 12. Do not raise or
            // lower either threshold without an explicit product/security
            // decision; the stronger requirement for privileged accounts is
            // intentional, not an oversight.
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'full_name' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:200'],
            'npwp_number' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:1000'],
            'phone_number' => ['required', 'string', 'max:20'],
            // Intent only; never grants a permission. BUYER_B2G must be granted
            // by an administrator after evidence review.
            'requested_role' => ['nullable', Rule::in([UserRole::BUYER_B2G->value])],
        ];
    }
}
