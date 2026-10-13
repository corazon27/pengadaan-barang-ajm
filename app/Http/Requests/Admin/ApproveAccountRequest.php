<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveAccountRequest extends FormRequest
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
            // Explicit approval choice. When the applicant requested B2G but the
            // administrator selects B2B, the choice is recorded and the request
            // is intentionally cleared (never silently).
            'role' => ['required', Rule::in([UserRole::BUYER_B2B->value, UserRole::BUYER_B2G->value])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
