<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pse;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pse\StorePseCertificateRequest;
use App\Http\Requests\Pse\UpdatePseCertificateRequest;
use App\Http\Resources\Pse\PseCertificateResource;
use App\Models\PSECertificate;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PseCertificateController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * List PSE Electronic Certificate registry records (PSE-CERT-001).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PSECertificate::class);

        $certificates = PSECertificate::query()->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar Sertifikat Elektronik PSE berhasil dimuat',
            'data' => PseCertificateResource::collection($certificates),
            'errors' => null,
        ], 200);
    }

    /**
     * Display a single PSE certificate record.
     */
    public function show(PSECertificate $certificate): JsonResponse
    {
        $this->authorize('view', $certificate);

        return response()->json([
            'success' => true,
            'message' => 'Sertifikat Elektronik PSE berhasil dimuat',
            'data' => new PseCertificateResource($certificate),
            'errors' => null,
        ], 200);
    }

    /**
     * Record a new PSE Electronic Certificate registry entry. AJM never
     * issues certificates; certificate_number is external PSrE evidence.
     */
    public function store(StorePseCertificateRequest $request): JsonResponse
    {
        $certificate = PSECertificate::create($request->validated());

        $this->auditLogger->log($request->user(), AuditAction::PSE_CERTIFICATE_CREATED, $certificate);

        return response()->json([
            'success' => true,
            'message' => 'Sertifikat Elektronik PSE berhasil dibuat',
            'data' => new PseCertificateResource($certificate),
            'errors' => null,
        ], 201);
    }

    /**
     * Update a PSE certificate registry entry (external lifecycle, internal
     * verification). certificate_status and verification_status are
     * independent and never inferred from each other.
     */
    public function update(UpdatePseCertificateRequest $request, PSECertificate $certificate): JsonResponse
    {
        $this->authorize('update', $certificate);

        $previous = $this->auditLogger->snapshot($certificate);
        $certificate->update($request->validated());

        $this->auditLogger->log($request->user(), AuditAction::PSE_CERTIFICATE_UPDATED, $certificate, $previous);

        return response()->json([
            'success' => true,
            'message' => 'Sertifikat Elektronik PSE berhasil diperbarui',
            'data' => new PseCertificateResource($certificate->fresh()),
            'errors' => null,
        ], 200);
    }
}
