<?php

declare(strict_types=1);

namespace App\Http\Requests\Pdp;

use App\Enums\ConsentSourceChannel;
use App\Enums\LawfulBasis;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsentRecordRequest extends FormRequest
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
            'subject_user_id' => ['required', 'uuid', 'exists:users,id'],
            'purpose' => ['required', 'string', 'max:200'],
            'processing_lawful_basis' => ['required', Rule::enum(LawfulBasis::class)],
            'notice_version' => ['required', 'string', 'max:50'],
            'document_ref' => ['required', 'string', 'max:255'],
            'source_channel' => ['required', Rule::enum(ConsentSourceChannel::class)],
            'rule_id' => ['nullable', 'string', 'max:50'],
            'withdrawal_deadline_at' => ['nullable', 'date'],
        ];
    }
}
