<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    use ApiResponser;

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return $this->errorResponse('Kredensial tidak valid.', null, 401);
        }

        /** @var User $user */
        $user = $request->user();

        $token = $user->createToken('auth-token', ['role:'.$user->role->value]);

        return $this->successResponse([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ], 'Login berhasil.');
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return $this->successResponse(null, 'Logout berhasil.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(new UserResource($request->user()), 'Profil pengguna.');
    }
}
