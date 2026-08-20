<?php

declare(strict_types=1);

namespace App\Http\Requests\Incident;

use Illuminate\Foundation\Http\FormRequest;

class SendBreachNotificationRequest extends FormRequest
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
        return [];
    }
}
