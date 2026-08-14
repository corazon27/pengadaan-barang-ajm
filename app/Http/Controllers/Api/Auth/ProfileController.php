<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Traits\ApiResponser;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    use ApiResponser;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $this->authorize('update', $user);

        $previous = $this->auditLogger->snapshot($user);
        $user->update($request->validated());

        $this->auditLogger->log($user, AuditAction::PROFILE_UPDATED, $user, $previous);

        return $this->successResponse(new UserResource($user->fresh()), 'Profil berhasil diperbarui.');
    }
}
