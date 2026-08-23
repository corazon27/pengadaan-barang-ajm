<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\LegalFunction;

use App\Http\Controllers\Controller;
use App\Http\Requests\LegalFunction\ApplyApplicabilityReviewRequest;
use App\Http\Requests\LegalFunction\OpenApplicabilityReviewRequest;
use App\Http\Requests\LegalFunction\StoreLegalFunctionRequest;
use App\Http\Requests\LegalFunction\TerminateLegalFunctionRequest;
use App\Http\Requests\LegalFunction\UpdateLegalFunctionRequest;
use App\Http\Resources\LegalFunction\LegalFunctionAssignmentResource;
use App\Models\LegalFunctionAssignment;
use App\Services\LegalFunctionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalFunctionController extends Controller
{
    public function __construct(
        private readonly LegalFunctionService $legalFunctionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LegalFunctionAssignment::class);

        $assignments = LegalFunctionAssignment::query()->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar penugasan fungsi hukum berhasil dimuat',
            'data' => LegalFunctionAssignmentResource::collection($assignments),
            'errors' => null,
        ], 200);
    }

    public function store(StoreLegalFunctionRequest $request): JsonResponse
    {
        $this->authorize('create', LegalFunctionAssignment::class);

        $assignment = $this->legalFunctionService->create(
            actor: $request->user(),
            data: $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Penugasan fungsi hukum berhasil dibuat',
            'data' => new LegalFunctionAssignmentResource($assignment),
            'errors' => null,
        ], 201);
    }

    public function show(LegalFunctionAssignment $legalFunction): JsonResponse
    {
        $this->authorize('view', $legalFunction);

        return response()->json([
            'success' => true,
            'message' => 'Penugasan fungsi hukum berhasil dimuat',
            'data' => new LegalFunctionAssignmentResource($legalFunction),
            'errors' => null,
        ], 200);
    }

    public function update(UpdateLegalFunctionRequest $request, LegalFunctionAssignment $legalFunction): JsonResponse
    {
        $this->authorize('update', $legalFunction);

        try {
            $assignment = $this->legalFunctionService->update(
                actor: $request->user(),
                assignment: $legalFunction,
                data: $request->validated(),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['update' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penugasan fungsi hukum berhasil diperbarui',
            'data' => new LegalFunctionAssignmentResource($assignment),
            'errors' => null,
        ], 200);
    }

    public function activate(LegalFunctionAssignment $legalFunction): JsonResponse
    {
        $this->authorize('activate', $legalFunction);

        try {
            $assignment = $this->legalFunctionService->activate(
                actor: request()->user(),
                assignment: $legalFunction,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penugasan fungsi hukum berhasil diaktifkan',
            'data' => new LegalFunctionAssignmentResource($assignment),
            'errors' => null,
        ], 200);
    }

    public function suspend(LegalFunctionAssignment $legalFunction): JsonResponse
    {
        $this->authorize('suspend', $legalFunction);

        try {
            $assignment = $this->legalFunctionService->suspend(
                actor: request()->user(),
                assignment: $legalFunction,
                reason: request()->input('reason', 'Suspended by operator'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penugasan fungsi hukum berhasil ditangguhkan',
            'data' => new LegalFunctionAssignmentResource($assignment),
            'errors' => null,
        ], 200);
    }

    public function terminate(TerminateLegalFunctionRequest $request, LegalFunctionAssignment $legalFunction): JsonResponse
    {
        $this->authorize('terminate', $legalFunction);

        try {
            $assignment = $this->legalFunctionService->terminate(
                actor: $request->user(),
                assignment: $legalFunction,
                reason: $request->validated('reason'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Penugasan fungsi hukum berhasil diakhiri',
            'data' => new LegalFunctionAssignmentResource($assignment),
            'errors' => null,
        ], 200);
    }

    public function openApplicabilityReview(OpenApplicabilityReviewRequest $request, LegalFunctionAssignment $legalFunction): JsonResponse
    {
        $this->authorize('openApplicabilityReview', $legalFunction);

        $case = $this->legalFunctionService->openApplicabilityReview(
            actor: $request->user(),
            assignment: $legalFunction,
            ruleId: $request->validated('rule_id'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Review keterpenuhan DPO berhasil dibuka',
            'data' => [
                'human_review_case_id' => $case->id,
                'status' => $case->status->value,
            ],
            'errors' => null,
        ], 201);
    }

    public function applyApplicabilityReview(ApplyApplicabilityReviewRequest $request, LegalFunctionAssignment $legalFunction): JsonResponse
    {
        $this->authorize('applyApplicabilityReview', $legalFunction);

        try {
            $assignment = $this->legalFunctionService->applyApplicabilityReview(
                actor: $request->user(),
                assignment: $legalFunction,
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
            'message' => 'Hasil review keterpenuhan DPO berhasil diterapkan',
            'data' => new LegalFunctionAssignmentResource($assignment),
            'errors' => null,
        ], 200);
    }
}
