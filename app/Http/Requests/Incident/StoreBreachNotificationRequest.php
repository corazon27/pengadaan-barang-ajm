<?php

declare(strict_types=1);

namespace App\Http\Requests\Incident;

use App\Enums\BreachNotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBreachNotificationRequest extends FormRequest
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
            'notification_type' => ['required', Rule::enum(BreachNotificationType::class)],
            'recipient' => ['required', 'string', 'max:255'],
            'content_snapshot' => ['nullable', 'array'],
        ];
    }
}
