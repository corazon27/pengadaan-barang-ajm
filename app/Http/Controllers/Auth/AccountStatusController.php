<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public, signed status page (24h). Read-only; no authentication, session, or
 * token is created. For REJECTED accounts it exposes a resubmit link; for
 * PENDING/REJECTED accounts it exposes an evidence-upload link.
 */
class AccountStatusController extends Controller
{
    public function show(Request $request, string $user): Response
    {
        $target = User::query()->find($user);

        if ($target === null) {
            abort(404);
        }

        $resubmitUrl = null;
        if ($target->status === UserStatus::REJECTED) {
            $resubmitUrl = URL::temporarySignedRoute('resubmit.show', now()->addHours(24), [
                'user' => $target->getKey(),
            ]);
        }

        $evidenceUrl = null;
        if (in_array($target->status, [UserStatus::PENDING, UserStatus::REJECTED], true)) {
            $evidenceUrl = URL::temporarySignedRoute('application.evidence.show', now()->addHours(24), [
                'user' => $target->getKey(),
            ]);
        }

        return Inertia::render('Auth/AccountStatus', [
            'email' => $target->email,
            'status' => $target->status->value,
            'resubmitUrl' => $resubmitUrl,
            'evidenceUrl' => $evidenceUrl,
        ]);
    }
}
