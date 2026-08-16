<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Enums\BuyerClassification;
use App\Enums\VatCollectorStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CreateOrderFromRfqRequest extends FormRequest
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
            'rfq_id' => ['required', 'string', 'exists:rfqs,id'],
            // Provisional Stage-1 commercial tax context captured at order time
            // (Phase 2E). Omitted values fall back to UNVERIFIED/NULL defaults
            // so resolution never invents a classification.
            'tax_context' => ['sometimes', 'array'],
            'tax_context.buyer_classification' => ['sometimes', 'string', Rule::in(array_column(BuyerClassification::cases(), 'value'))],
            'tax_context.collector_status' => ['sometimes', 'string', Rule::in(array_column(VatCollectorStatus::cases(), 'value'))],
            'tax_context.taxpayer_status' => ['sometimes', 'string', Rule::in(['PKP', 'NON_PKP', 'UNVERIFIED'])],
            'tax_context.transaction_type' => ['sometimes', 'string', 'max:255'],
            'tax_context.product_classification' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
