<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rejected-applicant resubmission via a separate signed link. GET renders a
 * form (no state change); POST re-applies the application. Prior decision
 * history is preserved by the service.
 */
class ApplicationResubmitController extends Controller
{
    public function show(Request $request, string $user): Response
    {
        $target = $this->resolveRejected($user);

        return Inertia::render('Auth/Resubmit', [
            'requestedRole' => $target->requested_role?->value,
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, string $user, AccountLifecycleService $service): RedirectResponse
    {
        $target = $this->resolveRejected($user);

        $validated = $request->validate([
            'requested_role' => ['nullable', Rule::in([UserRole::BUYER_B2G->value])],
        ]);

        $service->resubmit($target, $validated);

        return redirect()
            ->route('login')
            ->with('status', 'Pengajuan Anda telah dikirim ulang dan menunggu peninjauan administrator.');
    }

    private function resolveRejected(string $user): User
    {
        $target = User::query()->find($user);

        if ($target === null || $target->status !== UserStatus::REJECTED) {
            abort(403);
        }

        return $target;
    }
}
