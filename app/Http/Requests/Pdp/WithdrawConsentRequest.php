<?php

declare(strict_types=1);

namespace App\Http\Requests\Pdp;

use App\Enums\UserRole;
use App\Models\ConsentRecord;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $routeConsent = $this->route('consent');
        $user = $this->user();

        if ($user === null || $routeConsent === null) {
            return false;
        }

        $consent = $routeConsent instanceof ConsentRecord
            ? $routeConsent
            : ConsentRecord::find($routeConsent);

        if ($consent === null) {
            return false;
        }

        return $user->id === $consent->subject_user_id || $user->role === UserRole::SUPERADMIN;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
