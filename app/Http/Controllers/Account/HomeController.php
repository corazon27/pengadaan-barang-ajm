<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Home', [
            'account' => [
                'full_name' => $user->full_name,
                'email' => $user->email,
                'company_name' => $user->company_name,
                'role' => $user->role->value,
                'status' => $user->status->value,
            ],
        ]);
    }
}
