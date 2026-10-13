<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Notifications\VerifyEmailNotification;
use App\Services\AccountLifecycleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request, AccountLifecycleService $service): RedirectResponse
    {
        $user = $service->register($request->validated());

        try {
            $user->notify(new VerifyEmailNotification);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Pendaftaran berhasil. Periksa email Anda untuk verifikasi sebelum akun dapat disetujui.');
    }
}
