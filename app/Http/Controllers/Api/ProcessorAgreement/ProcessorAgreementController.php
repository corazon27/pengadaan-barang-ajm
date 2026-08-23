<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\ProcessorAgreement;

use App\Enums\AgreementStatus;
use App\Enums\ProcessorRoleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessorAgreement\ApplyRoleReviewRequest;
use App\Http\Requests\ProcessorAgreement\ChangeAgreementStatusRequest;
use App\Http\Requests\ProcessorAgreement\DetermineRoleRequest;
use App\Http\Requests\ProcessorAgreement\OpenRoleReviewRequest;
use App\Http\Requests\ProcessorAgreement\StoreProcessorAgreementRequest;
use App\Http\Requests\ProcessorAgreement\UpdateProcessorAgreementRequest;
use App\Http\Requests\ProcessorAgreement\VerifyEvidenceRequest;
use App\Http\Resources\ProcessorAgreement\ProcessorAgreementResource;
use App\Models\ProcessorAgreement;
use App\Services\ProcessorAgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcessorAgreementController extends Controller
{
    public function __construct(
        private readonly ProcessorAgreementService $processorAgreementService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ProcessorAgreement::class);

        $agreements = ProcessorAgreement::query()->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar perjanjian pemroses berhasil dimuat',
            'data' => ProcessorAgreementResource::collection($agreements),
            'errors' => null,
        ], 200);
    }

    public function store(StoreProcessorAgreementRequest $request): JsonResponse
    {
        $this->authorize('create', ProcessorAgreement::class);

        $agreement = $this->processorAgreementService->create(
            actor: $request->user(),
            data: $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Perjanjian pemroses berhasil dibuat',
            'data' => new ProcessorAgreementResource($agreement),
            'errors' => null,
        ], 201);
    }

    public function show(ProcessorAgreement $agreement): JsonResponse
    {
        $this->authorize('view', $agreement);

        return response()->json([
            'success' => true,
            'message' => 'Perjanjian pemroses berhasil dimuat',
            'data' => new ProcessorAgreementResource($agreement),
            'errors' => null,
        ], 200);
    }

    public function update(UpdateProcessorAgreementRequest $request, ProcessorAgreement $agreement): JsonResponse
    {
        $this->authorize('update', $agreement);

        $agreement = $this->processorAgreementService->update(
            actor: $request->user(),
            agreement: $agreement,
            data: $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Perjanjian pemroses berhasil diperbarui',
            'data' => new ProcessorAgreementResource($agreement),
            'errors' => null,
        ], 200);
    }

    public function changeAgreementStatus(ChangeAgreementStatusRequest $request, ProcessorAgreement $agreement): JsonResponse
    {
        $this->authorize('changeAgreementStatus', $agreement);

        try {
            $agreement = $this->processorAgreementService->changeAgreementStatus(
                actor: $request->user(),
                agreement: $agreement,
                targetStatus: AgreementStatus::from($request->validated('agreement_status')),
                reason: $request->validated('reason'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['agreement_status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status perjanjian pemroses berhasil diubah',
            'data' => new ProcessorAgreementResource($agreement),
            'errors' => null,
        ], 200);
    }

    public function verifyEvidence(VerifyEvidenceRequest $request, ProcessorAgreement $agreement): JsonResponse
    {
        $this->authorize('verifyEvidence', $agreement);

        try {
            $agreement = $this->processorAgreementService->verifyEvidence(
                actor: $request->user(),
                agreement: $agreement,
                factualBasis: $request->validated('factual_basis'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['evidence' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bukti berhasil diverifikasi',
            'data' => new ProcessorAgreementResource($agreement),
            'errors' => null,
        ], 200);
    }

    public function determineRole(DetermineRoleRequest $request, ProcessorAgreement $agreement): JsonResponse
    {
        $this->authorize('determineRole', $agreement);

        try {
            $agreement = $this->processorAgreementService->determineRole(
                actor: $request->user(),
                agreement: $agreement,
                targetRole: ProcessorRoleStatus::from($request->validated('processor_role_status')),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['processor_role_status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Peran pemroses berhasil ditentukan',
            'data' => new ProcessorAgreementResource($agreement),
            'errors' => null,
        ], 200);
    }

    public function openRoleReview(OpenRoleReviewRequest $request, ProcessorAgreement $agreement): JsonResponse
    {
        $this->authorize('openRoleReview', $agreement);

        $case = $this->processorAgreementService->openRoleReview(
            actor: $request->user(),
            agreement: $agreement,
            ruleId: $request->validated('rule_id'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Review peran pemroses berhasil dibuka',
            'data' => [
                'human_review_case_id' => $case->id,
                'status' => $case->status->value,
            ],
            'errors' => null,
        ], 201);
    }

    public function applyRoleReview(ApplyRoleReviewRequest $request, ProcessorAgreement $agreement): JsonResponse
    {
        $this->authorize('applyRoleReview', $agreement);

        try {
            $agreement = $this->processorAgreementService->applyRoleReview(
                actor: $request->user(),
                agreement: $agreement,
                humanReviewCaseId: $request->validated('human_review_case_id'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['human_review_case_id' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Hasil review peran pemroses berhasil diterapkan',
            'data' => new ProcessorAgreementResource($agreement),
            'errors' => null,
        ], 200);
    }
}
