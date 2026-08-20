<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Events\BreachQualifiedEvent;
use App\Events\HumanReviewCaseCreated;
use App\Models\HumanReviewCase;
use App\Models\IncidentRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IncidentService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Register a new incident. Initial status = DETECTED, breach qualification = UNKNOWN.
     */
    public function createIncident(
        User $actor,
        string $title,
        string $description,
        array $metadata = [],
    ): IncidentRegister {
        return DB::transaction(function () use ($actor, $title, $description, $metadata) {
            $incident = IncidentRegister::create([
                'title' => $title,
                'description' => $description,
                'affected_systems' => $metadata['affected_systems'] ?? null,
                'affected_data_categories' => $metadata['affected_data_categories'] ?? null,
                'number_of_subjects_known' => $metadata['number_of_subjects_known'] ?? null,
                'containment_status' => $metadata['containment_status'] ?? null,
                'evidence_snapshot' => $metadata['evidence_snapshot'] ?? null,
                'actor_user_id' => $actor->id,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::INCIDENT_CREATED,
                $incident,
                [],
                [
                    'incident_type' => $incident->incident_type?->value,
                    'severity' => $incident->severity?->value,
                    'status' => $incident->status->value,
                    'title' => $title,
                ],
            );

            return $incident;
        });
    }

    /**
     * Classify an incident with type and severity.
     * Transitions: DETECTED → TRIAGED → CLASSIFIED.
     */
    public function classifyIncident(
        IncidentRegister $incident,
        User $actor,
        IncidentType $incidentType,
        IncidentSeverity $severity,
    ): IncidentRegister {
        return DB::transaction(function () use ($incident, $actor, $incidentType, $severity) {
            $previousState = [
                'incident_type' => $incident->incident_type?->value,
                'severity' => $incident->severity?->value,
                'status' => $incident->status->value,
            ];

            $incident->update([
                'incident_type' => $incidentType,
                'severity' => $severity,
                'status' => IncidentStatus::CLASSIFIED,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::INCIDENT_CLASSIFIED,
                $incident,
                $previousState,
                [
                    'incident_type' => $incidentType->value,
                    'severity' => $severity->value,
                    'status' => IncidentStatus::CLASSIFIED->value,
                ],
            );

            return $incident->refresh();
        });
    }

    /**
     * Qualify breach status.
     * UNKNOWN → REQUIRE_REVIEW → QUALIFIED/NOT_QUALIFIED
     * UNKNOWN → QUALIFIED (clear breach)
     * UNKNOWN → NOT_QUALIFIED (clear non-breach)
     *
     * Only QUALIFIED emits BreachQualifiedEvent.
     */
    public function qualifyBreach(
        IncidentRegister $incident,
        User $actor,
        BreachQualificationStatus $qualification,
        ?string $reason = null,
    ): IncidentRegister {
        $this->validateQualificationTransition($incident, $qualification);

        return DB::transaction(function () use ($incident, $actor, $qualification, $reason) {
            $previousState = [
                'breach_qualification_status' => $incident->breach_qualification_status->value,
                'status' => $incident->status->value,
            ];

            $updateData = [
                'breach_qualification_status' => $qualification,
            ];

            if ($qualification === BreachQualificationStatus::QUALIFIED) {
                $updateData['breach_qualified_at'] = now();
                $updateData['status'] = IncidentStatus::CONFIRMED;
            } elseif ($qualification === BreachQualificationStatus::NOT_QUALIFIED) {
                $updateData['status'] = IncidentStatus::CONFIRMED;
            } elseif ($qualification === BreachQualificationStatus::REQUIRE_REVIEW) {
                $updateData['status'] = IncidentStatus::REVIEW_REQUIRED;
            }

            $incident->update($updateData);

            if ($qualification === BreachQualificationStatus::QUALIFIED) {
                event(new BreachQualifiedEvent(
                    sourceType: IncidentRegister::class,
                    sourceId: $incident->id,
                    detectedAt: $incident->created_at,
                    ruleId: 'PDP-BREACH-001',
                ));
            }

            $auditAction = $qualification === BreachQualificationStatus::QUALIFIED
                ? AuditAction::INCIDENT_BREACH_QUALIFIED
                : AuditAction::INCIDENT_BREACH_NOT_QUALIFIED;

            $this->auditLogger->log(
                $actor,
                $auditAction,
                $incident,
                $previousState,
                [
                    'breach_qualification_status' => $qualification->value,
                    'status' => $incident->status->value,
                    'reason' => $reason,
                ],
            );

            return $incident->refresh();
        });
    }

    /**
     * Route ambiguous qualification to HumanReviewCase.
     */
    public function openReview(
        IncidentRegister $incident,
        User $actor,
        string $trigger,
        ?string $ruleId = null,
    ): IncidentRegister {
        return DB::transaction(function () use ($incident, $actor, $trigger, $ruleId) {
            $case = HumanReviewCase::create([
                'type' => $incident->incident_type === IncidentType::PSE_DISRUPTION ? 'PSE' : 'PDP',
                'rule_id' => $ruleId,
                'trigger' => $trigger,
                'subject_type' => 'IncidentRegister',
                'subject_id' => $incident->id,
                'status' => 'PENDING',
            ]);

            $previousState = [
                'status' => $incident->status->value,
            ];

            $incident->update([
                'status' => IncidentStatus::REVIEW_REQUIRED,
                'breach_qualification_status' => BreachQualificationStatus::REQUIRE_REVIEW,
                'human_review_case_id' => $case->id,
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::INCIDENT_REVIEW_OPENED,
                $incident,
                $previousState,
                [
                    'status' => IncidentStatus::REVIEW_REQUIRED->value,
                    'human_review_case_id' => $case->id,
                ],
            );

            event(new HumanReviewCaseCreated($case));

            return $incident->refresh();
        });
    }

    /**
     * Resolve an incident (containment/mitigation complete).
     */
    public function resolveIncident(
        IncidentRegister $incident,
        User $actor,
        ?string $containmentStatus = null,
    ): IncidentRegister {
        return DB::transaction(function () use ($incident, $actor, $containmentStatus) {
            $previousState = [
                'status' => $incident->status->value,
                'containment_status' => $incident->containment_status,
            ];

            $updateData = [
                'status' => IncidentStatus::RESOLVED,
                'resolved_at' => now(),
            ];

            if ($containmentStatus !== null) {
                $updateData['containment_status'] = $containmentStatus;
            }

            $incident->update($updateData);

            $this->auditLogger->log(
                $actor,
                AuditAction::INCIDENT_RESOLVED,
                $incident,
                $previousState,
                [
                    'status' => IncidentStatus::RESOLVED->value,
                    'resolved_at' => now()->toAtomString(),
                ],
            );

            return $incident->refresh();
        });
    }

    /**
     * Close an incident (final administrative closure).
     */
    public function closeIncident(
        IncidentRegister $incident,
        User $actor,
    ): IncidentRegister {
        return DB::transaction(function () use ($incident, $actor) {
            $previousState = [
                'status' => $incident->status->value,
            ];

            $incident->update([
                'status' => IncidentStatus::CLOSED,
                'closed_at' => now(),
            ]);

            $this->auditLogger->log(
                $actor,
                AuditAction::INCIDENT_CLOSED,
                $incident,
                $previousState,
                [
                    'status' => IncidentStatus::CLOSED->value,
                    'closed_at' => now()->toAtomString(),
                ],
            );

            return $incident->refresh();
        });
    }

    /**
     * Validate breach qualification transition is allowed.
     */
    private function validateQualificationTransition(
        IncidentRegister $incident,
        BreachQualificationStatus $target,
    ): void {
        $current = $incident->breach_qualification_status;

        $allowedTransitions = [
            BreachQualificationStatus::UNKNOWN->value => [
                BreachQualificationStatus::QUALIFIED,
                BreachQualificationStatus::NOT_QUALIFIED,
                BreachQualificationStatus::REQUIRE_REVIEW,
            ],
            BreachQualificationStatus::REQUIRE_REVIEW->value => [
                BreachQualificationStatus::QUALIFIED,
                BreachQualificationStatus::NOT_QUALIFIED,
            ],
        ];

        $allowed = $allowedTransitions[$current->value] ?? [];

        if (! in_array($target, $allowed)) {
            throw new \InvalidArgumentException(
                "Invalid qualification transition from {$current->value} to {$target->value}"
            );
        }
    }
}
