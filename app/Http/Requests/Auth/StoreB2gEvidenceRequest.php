<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class StoreB2gEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Guest, signed-link route: authorization is enforced by the `signed`
        // middleware and the controller's applicant-status check, not a session.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The permitted evidence-type catalogue is a business/legal decision
            // still pending approval; this build validates a bounded, non-empty
            // label only. Production B2G activation stays blocked until the
            // approved checklist is supplied.
            'evidence_type' => ['required', 'string', 'max:100'],
            'institution_name' => ['nullable', 'string', 'max:200'],
            'work_unit' => ['nullable', 'string', 'max:200'],
            'position' => ['nullable', 'string', 'max:200'],
            'reference' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
