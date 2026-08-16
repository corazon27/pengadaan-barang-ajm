<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pse;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pse\StorePseRegistrationRequest;
use App\Http\Requests\Pse\UpdatePseRegistrationRequest;
use App\Http\Resources\Pse\PseRegistrationResource;
use App\Models\PSERegistration;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PseRegistrationController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * List PSE registration registry records (PSE-REG-001/002/003).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PSERegistration::class);

        $registrations = PSERegistration::query()->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar registrasi PSE berhasil dimuat',
            'data' => PseRegistrationResource::collection($registrations),
            'errors' => null,
        ], 200);
    }

    /**
     * Display a single PSE registration record.
     */
    public function show(PSERegistration $registration): JsonResponse
    {
        $this->authorize('view', $registration);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi PSE berhasil dimuat',
            'data' => new PseRegistrationResource($registration),
            'errors' => null,
        ], 200);
    }

    /**
     * Record a new PSE registration registry entry.
     */
    public function store(StorePseRegistrationRequest $request): JsonResponse
    {
        $registration = PSERegistration::create($request->validated());

        $this->auditLogger->log($request->user(), AuditAction::PSE_REGISTRATION_CREATED, $registration);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi PSE berhasil dibuat',
            'data' => new PseRegistrationResource($registration),
            'errors' => null,
        ], 201);
    }

    /**
     * Update a PSE registration registry entry (data maintenance, PSE-REG-003).
     */
    public function update(UpdatePseRegistrationRequest $request, PSERegistration $registration): JsonResponse
    {
        $this->authorize('update', $registration);

        $previous = $this->auditLogger->snapshot($registration);
        $registration->update($request->validated());

        $this->auditLogger->log($request->user(), AuditAction::PSE_REGISTRATION_UPDATED, $registration, $previous);

        return response()->json([
            'success' => true,
            'message' => 'Registrasi PSE berhasil diperbarui',
            'data' => new PseRegistrationResource($registration->fresh()),
            'errors' => null,
        ], 200);
    }
}
