<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    use ApiResponser;

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        $user->update($request->validated());

        return $this->successResponse(new UserResource($user->fresh()), 'Profil berhasil diperbarui.');
    }
}
