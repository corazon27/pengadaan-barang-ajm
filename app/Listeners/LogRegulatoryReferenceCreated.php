<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\AuditAction;
use App\Events\RegulatoryReferenceCreated;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Routes a RegulatoryReferenceCreated event through the EXISTING Module 8
 * AuditLogger substrate. No new audit store, no schema change, no
 * polymorphic join — exactly one canonical audit path.
 */
class LogRegulatoryReferenceCreated implements ShouldQueue
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(RegulatoryReferenceCreated $event): void
    {
        $this->auditLogger->log(null, AuditAction::REGULATORY_REFERENCE_CREATED, $event->reference);
    }
}
