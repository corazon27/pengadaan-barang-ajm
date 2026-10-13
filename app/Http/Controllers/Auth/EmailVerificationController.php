<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\AccountLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Guest email verification.
 *
 * GET only renders a confirmation page and performs NO state change. The
 * explicit POST performs verification after re-validating the signed link, the
 * user identity, and the current email binding. Neither request authenticates
 * the account, creates a session/token, changes status, or grants a role.
 */
class EmailVerificationController extends Controller
{
    public function show(Request $request, string $user, string $hash): Response
    {
        $target = $this->resolveTarget($user, $hash);

        return Inertia::render('Auth/VerifyConfirm', [
            'email' => $target->email,
            'alreadyVerified' => $target->hasVerifiedEmail(),
            // Same signed path is reused for the explicit POST confirmation.
            'actionUrl' => $request->fullUrl(),
        ]);
    }

    public function verify(string $user, string $hash, AccountLifecycleService $service): RedirectResponse
    {
        $target = $this->resolveTarget($user, $hash);

        $service->markEmailVerified($target);

        return redirect()
            ->route('login')
            ->with('status', 'Email berhasil diverifikasi. Akun Anda menunggu persetujuan administrator.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $target = User::query()->where('email', $request->input('email'))->first();

        if ($target !== null && ! $target->hasVerifiedEmail()) {
            try {
                $target->notify(new VerifyEmailNotification);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Generic response: never discloses whether the account exists.
        return back()->with('status', 'Jika email terdaftar, tautan verifikasi telah dikirim.');
    }

    private function resolveTarget(string $user, string $hash): User
    {
        $target = User::query()->find($user);

        if ($target === null || ! hash_equals($target->emailVerificationHash(), $hash)) {
            abort(403);
        }

        return $target;
    }
}
