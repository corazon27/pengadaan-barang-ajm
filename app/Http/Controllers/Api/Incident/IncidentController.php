<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Incident;

use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Incident\ClassifyIncidentRequest;
use App\Http\Requests\Incident\QualifyBreachRequest;
use App\Http\Requests\Incident\StoreIncidentRequest;
use App\Http\Requests\Incident\UpdateIncidentRequest;
use App\Http\Resources\Incident\IncidentResource;
use App\Models\IncidentRegister;
use App\Services\IncidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function __construct(
        private readonly IncidentService $incidentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', IncidentRegister::class);

        $incidents = IncidentRegister::query()->paginate($this->perPage($request));

        return response()->json([
            'success' => true,
            'message' => 'Daftar insiden berhasil dimuat',
            'data' => IncidentResource::collection($incidents),
            'errors' => null,
        ], 200);
    }

    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $this->authorize('create', IncidentRegister::class);

        $incident = $this->incidentService->createIncident(
            actor: $request->user(),
            title: $request->validated('title'),
            description: $request->validated('description'),
            metadata: [
                'affected_systems' => $request->validated('affected_systems'),
                'affected_data_categories' => $request->validated('affected_data_categories'),
                'number_of_subjects_known' => $request->validated('number_of_subjects_known'),
                'containment_status' => $request->validated('containment_status'),
                'evidence_snapshot' => $request->validated('evidence_snapshot'),
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Insiden berhasil didaftarkan',
            'data' => new IncidentResource($incident),
            'errors' => null,
        ], 201);
    }

    public function show(IncidentRegister $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        return response()->json([
            'success' => true,
            'message' => 'Insiden berhasil dimuat',
            'data' => new IncidentResource($incident),
            'errors' => null,
        ], 200);
    }

    public function update(UpdateIncidentRequest $request, IncidentRegister $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $incident->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Insiden berhasil diperbarui',
            'data' => new IncidentResource($incident->refresh()),
            'errors' => null,
        ], 200);
    }

    public function classify(ClassifyIncidentRequest $request, IncidentRegister $incident): JsonResponse
    {
        $this->authorize('classify', $incident);

        $incident = $this->incidentService->classifyIncident(
            incident: $incident,
            actor: $request->user(),
            incidentType: IncidentType::from($request->validated('incident_type')),
            severity: IncidentSeverity::from($request->validated('severity')),
        );

        return response()->json([
            'success' => true,
            'message' => 'Insiden berhasil diklasifikasi',
            'data' => new IncidentResource($incident),
            'errors' => null,
        ], 200);
    }

    public function qualifyBreach(QualifyBreachRequest $request, IncidentRegister $incident): JsonResponse
    {
        $this->authorize('qualifyBreach', $incident);

        try {
            $incident = $this->incidentService->qualifyBreach(
                incident: $incident,
                actor: $request->user(),
                qualification: BreachQualificationStatus::from($request->validated('breach_qualification_status')),
                reason: $request->validated('reason'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => ['breach_qualification_status' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kualifikasi pelanggaran berhasil ditentukan',
            'data' => new IncidentResource($incident),
            'errors' => null,
        ], 200);
    }

    public function resolve(Request $request, IncidentRegister $incident): JsonResponse
    {
        $this->authorize('resolve', $incident);

        $validated = $request->validate([
            'containment_status' => ['nullable', 'string', 'max:50'],
        ]);

        $incident = $this->incidentService->resolveIncident(
            incident: $incident,
            actor: $request->user(),
            containmentStatus: $validated['containment_status'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Insiden berhasil diselesaikan',
            'data' => new IncidentResource($incident),
            'errors' => null,
        ], 200);
    }

    public function close(Request $request, IncidentRegister $incident): JsonResponse
    {
        $this->authorize('close', $incident);

        $incident = $this->incidentService->closeIncident(
            incident: $incident,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Insiden berhasil ditutup',
            'data' => new IncidentResource($incident),
            'errors' => null,
        ], 200);
    }
}
