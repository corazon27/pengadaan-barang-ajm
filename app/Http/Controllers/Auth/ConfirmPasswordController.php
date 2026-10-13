<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Re-confirms the current password so the framework `password.confirm`
 * middleware can guard high-impact admin actions.
 */
class ConfirmPasswordController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('account.home'));
    }
}
