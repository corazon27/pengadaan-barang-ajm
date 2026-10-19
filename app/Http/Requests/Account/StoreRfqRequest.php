<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Models\Rfq;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Web (Inertia) buyer RFQ submission. Validation mirrors the API
 * `App\Http\Requests\Rfq\StoreRfqRequest`; authorization uses the narrower
 * `RfqPolicy::createAsBuyer` ability so SUPERADMIN cannot use this surface.
 */
class StoreRfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createAsBuyer', Rfq::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.target_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ];
    }
}
