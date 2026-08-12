<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateInvoicePaymentStatusRequest extends FormRequest
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
            'payment_status' => ['required', 'string', 'in:'.implode(',', array_column(InvoiceStatus::cases(), 'value'))],
        ];
    }
}
