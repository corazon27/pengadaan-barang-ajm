<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AuditLog;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLog\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Display a listing of the audit log entries.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('user');

        // Filter by entity type
        if ($entityType = $request->input('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        // Filter by action
        if ($action = $request->input('action')) {
            if (AuditAction::tryFrom($action) === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validasi gagal.',
                    'data' => null,
                    'errors' => ['action' => ['Aksi audit tidak valid.']],
                ], 422);
            }

            $query->where('action', $action);
        }

        $perPage = $this->perPage($request);
        $logs = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Audit log listing retrieved',
            'data' => AuditLogResource::collection($logs),
            'errors' => null,
        ], 200);
    }
}
