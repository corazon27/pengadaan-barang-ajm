<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Resolve the requested page size, clamped to 1..100 (default 15) so a
     * client cannot force a single unbounded listing query.
     */
    protected function perPage(?Request $request = null): int
    {
        $perPage = (int) $request?->input('per_page', 15);

        return min(100, max(1, $perPage));
    }
}
