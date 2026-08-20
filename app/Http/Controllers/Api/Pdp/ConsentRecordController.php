<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Pdp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pdp\StoreConsentRecordRequest;
use App\Http\Requests\Pdp\WithdrawConsentRequest;
use App\Http\Resources\Pdp\ConsentRecordResource;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsentRecordController extends Controller
{
    public function __construct(
        private readonly ConsentService $consentService,
    ) {}

    /**
     * List all consent records (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConsentRecord::class);

        $records = ConsentRecord::query()->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar Consent Record berhasil dimuat',
            'data' => ConsentRecordResource::collection($records),
            'errors' => null,
        ], 200);
    }

    /**
     * Display a single consent record (admin or subject-self).
     */
    public function show(ConsentRecord $consent): JsonResponse
    {
        $this->authorize('view', $consent);

        return response()->json([
            'success' => true,
            'message' => 'Consent Record berhasil dimuat',
            'data' => new ConsentRecordResource($consent),
            'errors' => null,
        ], 200);
    }

    /**
     * Create a new consent record (admin only).
     */
    public function store(StoreConsentRecordRequest $request): JsonResponse
    {
        $subject = User::findOrFail($request->validated('subject_user_id'));

        $record = $this->consentService->grant(
            subject: $subject,
            purpose: $request->validated('purpose'),
            processingLawfulBasis: $request->validated('processing_lawful_basis'),
            noticeVersion: $request->validated('notice_version'),
            documentRef: $request->validated('document_ref'),
            sourceChannel: $request->validated('source_channel'),
            actor: $request->user(),
            ruleId: $request->validated('rule_id'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Consent Record berhasil dibuat',
            'data' => new ConsentRecordResource($record),
            'errors' => null,
        ], 201);
    }

    /**
     * Withdraw a consent record (subject-self or admin).
     */
    public function withdraw(WithdrawConsentRequest $request, ConsentRecord $consent): JsonResponse
    {
        $record = $this->consentService->withdraw(
            record: $consent,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Consent berhasil ditarik',
            'data' => new ConsentRecordResource($record),
            'errors' => null,
        ], 200);
    }

    /**
     * Supersede an existing consent (admin only).
     */
    public function supersede(Request $request, ConsentRecord $consent): JsonResponse
    {
        $this->authorize('update', $consent);

        $validated = $request->validate([
            'purpose' => 'sometimes|string|max:200',
            'processing_lawful_basis' => 'sometimes|string|max:30',
            'notice_version' => 'sometimes|string|max:50',
            'document_ref' => 'sometimes|string|max:255',
            'source_channel' => 'sometimes|string|max:20',
            'rule_id' => 'nullable|string|max:50',
        ]);

        $newRecord = $this->consentService->supersede(
            old: $consent,
            newAttributes: $validated,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Consent berhasil di-supersede',
            'data' => new ConsentRecordResource($newRecord),
            'errors' => null,
        ], 200);
    }

    /**
     * Invalidate a consent record (admin only).
     */
    public function invalidate(Request $request, ConsentRecord $consent): JsonResponse
    {
        $this->authorize('update', $consent);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $record = $this->consentService->invalidate(
            record: $consent,
            actor: $request->user(),
            reason: $validated['reason'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Consent berhasil di-invalidate',
            'data' => new ConsentRecordResource($record),
            'errors' => null,
        ], 200);
    }
}
