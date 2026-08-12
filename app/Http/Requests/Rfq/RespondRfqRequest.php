<?php

declare(strict_types=1);

namespace App\Http\Requests\Rfq;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class RespondRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()?->role === UserRole::SUPERADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admin_notes' => ['nullable', 'string'],
            'valid_until' => ['required', 'date', 'after:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.rfq_item_id' => ['required', 'uuid', 'exists:rfq_items,id'],
            'items.*.offered_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
