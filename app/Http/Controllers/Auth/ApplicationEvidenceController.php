<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreB2gEvidenceRequest;
use App\Models\User;
use App\Services\B2gEvidenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Applicant self-service B2G evidence upload via a signed link, available while
 * the account is PENDING or REJECTED. GET renders the form (no state change);
 * POST stores a new private, versioned submission. Uploading evidence does not
 * change account status or grant any role.
 */
class ApplicationEvidenceController extends Controller
{
    public function show(Request $request, string $user): Response
    {
        $target = $this->resolveApplicant($user);

        return Inertia::render('Auth/Evidence', [
            'actionUrl' => $request->fullUrl(),
            'requestedRole' => $target->requested_role?->value,
        ]);
    }

    public function store(StoreB2gEvidenceRequest $request, string $user, B2gEvidenceService $service): RedirectResponse
    {
        $target = $this->resolveApplicant($user);

        $service->submit($target, $request->validated(), $request->file('file'));

        return redirect()
            ->route('login')
            ->with('status', 'Bukti B2G berhasil diunggah dan menunggu peninjauan administrator.');
    }

    private function resolveApplicant(string $user): User
    {
        $target = User::query()->find($user);

        if ($target === null || ! in_array($target->status, [UserStatus::PENDING, UserStatus::REJECTED], true)) {
            abort(403);
        }

        return $target;
    }
}
