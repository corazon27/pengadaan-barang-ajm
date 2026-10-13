<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies operational access to any authenticated account that is not ACTIVE.
 *
 * Applied to both the browser (session `web`) and API (`auth:sanctum`) surfaces,
 * AFTER authentication has resolved the user. This closes the gap where a token
 * issued while ACTIVE would otherwise keep working after suspension, and where a
 * stale browser session could survive a status change.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->status !== UserStatus::ACTIVE) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak aktif. Hubungi administrator.',
                    'data' => null,
                    'errors' => ['account' => ['Akun Anda tidak aktif.']],
                ], 403);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Akun Anda tidak aktif. Silakan hubungi administrator.');
        }

        return $next($request);
    }
}
