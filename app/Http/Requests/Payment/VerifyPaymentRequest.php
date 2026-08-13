<?php

declare(strict_types=1);

namespace App\Http\Requests\Payment;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class VerifyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.PaymentStatus::VERIFIED->value.','.PaymentStatus::REJECTED->value],
            'rejection_reason' => ['required_if:status,'.PaymentStatus::REJECTED->value, 'string', 'max:1000'],
        ];
    }
}
