<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Analytics;

use App\Http\Controllers\Controller;
use App\Policies\AnalyticsPolicy;
use App\Services\AnalyticsMetricsService;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsMetricsService $metrics,
    ) {}

    /**
     * Executive dashboard metrics for Superadmins.
     */
    public function dashboard(): JsonResponse
    {
        $this->authorize('view', AnalyticsPolicy::class);

        return response()->json([
            'success' => true,
            'message' => 'Analytics dashboard retrieved',
            'data' => $this->metrics->snapshot(),
            'errors' => null,
        ], 200);
    }
}
