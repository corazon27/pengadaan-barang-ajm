<?php

declare(strict_types=1);

namespace App\Http\Requests\Pdp;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class FulfillDataSubjectRequestRequest extends FormRequest
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
            'decision_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
