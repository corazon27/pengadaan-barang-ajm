<?php

declare(strict_types=1);

namespace App\Http\Requests\Pdp;

use App\Enums\DsrChannel;
use App\Enums\SubjectType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataSubjectRequestRequest extends FormRequest
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
            'subject_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'subject_type' => ['required', Rule::enum(SubjectType::class)],
            'right_code' => ['required', 'string', 'max:30'],
            'channel' => ['required', Rule::enum(DsrChannel::class)],
            'request_input' => ['nullable', 'array'],
        ];
    }
}
