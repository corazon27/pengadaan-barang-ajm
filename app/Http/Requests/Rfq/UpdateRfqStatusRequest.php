<?php

declare(strict_types=1);

namespace App\Http\Requests\Rfq;

use App\Enums\RfqStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateRfqStatusRequest extends FormRequest
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
            'status' => ['required', 'string', 'in:'.implode(',', array_map(fn (RfqStatus $s) => $s->value, RfqStatus::cases()))],
        ];
    }
}
