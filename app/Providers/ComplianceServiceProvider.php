<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\HumanReviewCase;
use App\Models\LegalFunctionAssignment;
use App\Models\RegulatoryReference;
use Illuminate\Support\ServiceProvider;

/**
 * Compliance Kernel service provider.
 *
 * PHASE 1 — infrastructure only:
 * - binds the Compliance Kernel models into the container
 * - registers any later Phase event/listener wiring
 * - does NOT touch audit_logs schema (Module 8 AuditLogger remains the
 *   single audit substrate; no polymorphic joins are introduced)
 */
class ComplianceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RegulatoryReference::class);
        $this->app->singleton(HumanReviewCase::class);
        $this->app->singleton(LegalFunctionAssignment::class);
    }

    public function boot(): void
    {
        //
    }
}
