<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pdp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pdp\FulfillDataSubjectRequestRequest;
use App\Http\Requests\Pdp\StoreDataSubjectRequestRequest;
use App\Http\Resources\Pdp\DataSubjectRequestResource;
use App\Models\DataSubjectRequest;
use App\Services\DataSubjectRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSubjectRequestController extends Controller
{
    public function __construct(
        private readonly DataSubjectRequestService $dsrService,
    ) {}

    /**
     * List all DSRs (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DataSubjectRequest::class);

        $dsrs = DataSubjectRequest::query()->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar Data Subject Request berhasil dimuat',
            'data' => DataSubjectRequestResource::collection($dsrs),
            'errors' => null,
        ], 200);
    }

    /**
     * Display a single DSR (admin or subject-self).
     */
    public function show(DataSubjectRequest $dsr): JsonResponse
    {
        $this->authorize('view', $dsr);

        return response()->json([
            'success' => true,
            'message' => 'Data Subject Request berhasil dimuat',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 200);
    }

    /**
     * Create a new DSR (any authenticated user).
     */
    public function store(StoreDataSubjectRequestRequest $request): JsonResponse
    {
        $dsr = $this->dsrService->createDsr(
            actor: $request->user(),
            subject: $request->user(),
            rightCode: $request->validated('right_code'),
            channel: $request->validated('channel'),
            requestInput: $request->validated('request_input'),
            subjectType: $request->validated('subject_type'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Data Subject Request berhasil dibuat',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 201);
    }

    /**
     * Verify identity for a DSR (admin only).
     */
    public function verifyIdentity(Request $request, DataSubjectRequest $dsr): JsonResponse
    {
        $this->authorize('update', $dsr);

        $validated = $request->validate([
            'identity_verification_status' => 'required|string|in:VERIFIED,UNVERIFIED,NOT_APPLICABLE',
            'identity_verification_meta' => 'nullable|array',
        ]);

        $dsr = $this->dsrService->verifyIdentity(
            dsr: $dsr,
            actor: $request->user(),
            status: $validated['identity_verification_status'],
            meta: $validated['identity_verification_meta'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Verifikasi identitas berhasil diperbarui',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 200);
    }

    /**
     * Classify right applicability (admin only).
     */
    public function classifyRight(Request $request, DataSubjectRequest $dsr): JsonResponse
    {
        $this->authorize('update', $dsr);

        $validated = $request->validate([
            'applicability_status' => 'required|string|in:CONFIRMED,REVIEW_REQUIRED,UNRESOLVED,PENDING_LEGAL_REVIEW,APPLICABILITY_UNKNOWN',
        ]);

        $dsr = $this->dsrService->classifyRight(
            dsr: $dsr,
            actor: $request->user(),
            applicabilityStatus: $validated['applicability_status'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Klasifikasi hak berhasil diperbarui',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 200);
    }

    /**
     * Resolve lawful basis for affected processing (admin only).
     */
    public function resolveLawfulBasis(Request $request, DataSubjectRequest $dsr): JsonResponse
    {
        $this->authorize('update', $dsr);

        $validated = $request->validate([
            'processing_class' => 'required|string|max:50',
        ]);

        $dsr = $this->dsrService->resolveLawfulBasis(
            dsr: $dsr,
            actor: $request->user(),
            processingClass: $validated['processing_class'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Lawful basis berhasil di-resolve',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 200);
    }

    /**
     * Open human review for a DSR (admin only).
     */
    public function openHumanReview(Request $request, DataSubjectRequest $dsr): JsonResponse
    {
        $this->authorize('update', $dsr);

        $validated = $request->validate([
            'decision_type' => 'required|string|max:50',
            'rule_id' => 'required|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $dsr = $this->dsrService->openHumanReview(
            dsr: $dsr,
            actor: $request->user(),
            decisionType: $validated['decision_type'],
            ruleId: $validated['rule_id'],
            notes: $validated['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Human review berhasil dibuka',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 200);
    }

    /**
     * Fulfill a DSR (admin only).
     */
    public function fulfill(FulfillDataSubjectRequestRequest $request, DataSubjectRequest $dsr): JsonResponse
    {
        $this->authorize('update', $dsr);

        $dsr = $this->dsrService->fulfill(
            dsr: $dsr,
            actor: $request->user(),
            notes: $request->validated('decision_notes'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Data Subject Request berhasil dipenuhi',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 200);
    }

    /**
     * Reject a DSR (admin only).
     */
    public function reject(FulfillDataSubjectRequestRequest $request, DataSubjectRequest $dsr): JsonResponse
    {
        $this->authorize('update', $dsr);

        $dsr = $this->dsrService->reject(
            dsr: $dsr,
            actor: $request->user(),
            notes: $request->validated('decision_notes'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Data Subject Request berhasil ditolak',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 200);
    }

    /**
     * Close a DSR (admin only).
     */
    public function close(FulfillDataSubjectRequestRequest $request, DataSubjectRequest $dsr): JsonResponse
    {
        $this->authorize('update', $dsr);

        $dsr = $this->dsrService->close(
            dsr: $dsr,
            actor: $request->user(),
            notes: $request->validated('decision_notes'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Data Subject Request berhasil ditutup',
            'data' => new DataSubjectRequestResource($dsr),
            'errors' => null,
        ], 200);
    }
}
