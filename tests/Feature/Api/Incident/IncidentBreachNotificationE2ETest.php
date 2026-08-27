<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Incident;

use App\Enums\AuditAction;
use App\Enums\BreachNotificationStatus;
use App\Enums\BreachNotificationType;
use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Events\BreachQualifiedEvent;
use App\Models\IncidentRegister;
use App\Models\StatutoryTimer;
use App\Models\User;
use App\Services\BreachNotificationService;
use App\Services\IncidentService;
use App\Services\StatutoryTimerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E2E integration test: incident → breach qualification → explicit breach
 * notification timer → AUTHORITY notification → timer MET.
 *
 * ARCHITECTURE NOTE (Option A — explicit timer creation):
 * BreachQualifiedEvent currently has NO production listener.
 * StatutoryTimerService::startForBreachNotification() is called explicitly
 * in this test to establish the timer prerequisite. This documents the current
 * production path: timer creation is NOT automatic on breach qualification.
 * If the approved architecture requires automatic timer creation on QUALIFIED,
 * a BreachQualifiedEvent listener is a REAL PRODUCTION DEFECT (see Option B).
 */
class IncidentBreachNotificationE2ETest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private IncidentService $incidentService;

    private BreachNotificationService $notificationService;

    private StatutoryTimerService $timerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->superadmin()->create();
        $this->incidentService = app(IncidentService::class);
        $this->notificationService = app(BreachNotificationService::class);
        $this->timerService = app(StatutoryTimerService::class);
    }

    public function test_incident_breach_qualified_with_explicit_timer_notification_sent_marks_timer_met(): void
    {
        // Step 1: Create incident
        $incident = $this->incidentService->createIncident(
            actor: $this->superadmin,
            title: 'PII data exposure via misconfigured S3 bucket',
            description: 'Customer records exposed to public internet for 48 hours',
            metadata: [
                'affected_systems' => ['s3-production'],
                'affected_data_categories' => ['NAME', 'EMAIL', 'NATIONAL_ID'],
                'number_of_subjects_known' => 1500,
            ],
        );

        $this->assertSame(IncidentStatus::DETECTED, $incident->status);
        $this->assertSame(BreachQualificationStatus::UNKNOWN, $incident->breach_qualification_status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_CREATED->value,
            'entity_type' => 'IncidentRegister',
            'entity_id' => $incident->id,
        ]);

        // Step 2: Classify incident
        $incident = $this->incidentService->classifyIncident(
            incident: $incident,
            actor: $this->superadmin,
            incidentType: IncidentType::SECURITY_INCIDENT,
            severity: IncidentSeverity::CRITICAL,
        );

        $this->assertSame(IncidentStatus::CLASSIFIED, $incident->status);
        $this->assertSame(IncidentType::SECURITY_INCIDENT, $incident->incident_type);
        $this->assertSame(IncidentSeverity::CRITICAL, $incident->severity);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_CLASSIFIED->value,
            'entity_type' => 'IncidentRegister',
            'entity_id' => $incident->id,
        ]);

        // Step 3: Qualify breach
        $incident = $this->incidentService->qualifyBreach(
            incident: $incident,
            actor: $this->superadmin,
            qualification: BreachQualificationStatus::QUALIFIED,
            reason: 'Personal data of 1500 subjects exposed without encryption',
        );

        $this->assertSame(IncidentStatus::CONFIRMED, $incident->status);
        $this->assertSame(BreachQualificationStatus::QUALIFIED, $incident->breach_qualification_status);
        $this->assertNotNull($incident->breach_qualified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_BREACH_QUALIFIED->value,
            'entity_type' => 'IncidentRegister',
            'entity_id' => $incident->id,
        ]);

        // Step 4: Establish breach notification timer via explicit production API
        //
        // ARCHITECTURE: BreachQualifiedEvent has NO listener. The timer is
        // created explicitly through StatutoryTimerService::startForBreachNotification().
        // This is the current production prerequisite — NOT an automatic chain.
        // If automatic timer creation is required, a listener must be added
        // (classified as REAL PRODUCTION DEFECT under Option B).
        $event = new BreachQualifiedEvent(
            sourceType: IncidentRegister::class,
            sourceId: $incident->id,
            detectedAt: $incident->created_at,
            ruleId: 'PDP-BREACH-001',
        );

        $this->timerService->startForBreachNotification($event);

        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION->value,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT->value,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $incident->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TIMER_CREATED->value,
            'entity_type' => 'StatutoryTimer',
        ]);

        // Step 5: Prepare AUTHORITY breach notification
        $notification = $this->notificationService->prepareNotification(
            incident: $incident,
            actor: $this->superadmin,
            type: BreachNotificationType::AUTHORITY,
            recipient: 'kominfo@authority.go.id',
            contentSnapshot: [
                'subject' => 'Data Breach Notification - PDP Compliance',
                'incident_date' => $incident->created_at->toAtomString(),
                'affected_subjects' => 1500,
            ],
        );

        $this->assertSame(BreachNotificationStatus::PENDING, $notification->status);
        $this->assertSame(BreachNotificationType::AUTHORITY, $notification->notification_type);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::BREACH_NOTIFICATION_PREPARED->value,
            'entity_type' => 'BreachNotification',
            'entity_id' => $notification->id,
        ]);

        // Step 6: Transition through PREPARING → READY
        $notification = $this->notificationService->markPreparing($notification, $this->superadmin);
        $this->assertSame(BreachNotificationStatus::PREPARING, $notification->status);

        $notification = $this->notificationService->markReady($notification, $this->superadmin);
        $this->assertSame(BreachNotificationStatus::READY, $notification->status);

        // Step 7: Send AUTHORITY notification
        // This dispatches BreachNotificationSent, which triggers
        // SignalBreachNotificationSent listener (sync queue in tests),
        // which marks the running breach notification timer as MET.
        $notification = $this->notificationService->sendNotification($notification, $this->superadmin);

        $this->assertSame(BreachNotificationStatus::SENT, $notification->status);
        $this->assertNotNull($notification->sent_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::BREACH_NOTIFICATION_SENT->value,
            'entity_type' => 'BreachNotification',
            'entity_id' => $notification->id,
        ]);

        // Step 8: Verify breach notification timer is now MET
        //
        // SignalBreachNotificationSent listener found the RUNNING timer
        // for this incident and called StatutoryTimerService::markMet().
        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION->value,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $incident->id,
            'status' => StatutoryTimerStatus::MET->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TIMER_MET->value,
            'entity_type' => 'StatutoryTimer',
        ]);

        // Step 9: Verify exactly one timer exists (no duplicates)
        $timerCount = StatutoryTimer::where('ref_type', IncidentRegister::class)
            ->where('ref_id', $incident->id)
            ->count();
        $this->assertSame(1, $timerCount);

        // Step 10: Verify final incident state
        $incident->refresh();
        $this->assertSame(IncidentStatus::CONFIRMED, $incident->status);
        $this->assertSame(BreachQualificationStatus::QUALIFIED, $incident->breach_qualification_status);
    }

    public function test_non_authority_notification_does_not_mark_timer_met(): void
    {
        $incident = $this->incidentService->createIncident(
            actor: $this->superadmin,
            title: 'Minor data exposure',
            description: 'Limited scope incident',
        );

        $incident = $this->incidentService->classifyIncident(
            incident: $incident,
            actor: $this->superadmin,
            incidentType: IncidentType::PDP_FAILURE,
            severity: IncidentSeverity::LOW,
        );

        $incident = $this->incidentService->qualifyBreach(
            incident: $incident,
            actor: $this->superadmin,
            qualification: BreachQualificationStatus::QUALIFIED,
            reason: 'Minor breach',
        );

        // Establish breach notification timer via explicit production API
        // (no automatic listener on BreachQualifiedEvent — see Option A architecture note)
        $event = new BreachQualifiedEvent(
            sourceType: IncidentRegister::class,
            sourceId: $incident->id,
            detectedAt: $incident->created_at,
            ruleId: 'PDP-BREACH-001',
        );
        $this->timerService->startForBreachNotification($event);

        // Prepare and send DATA_SUBJECT notification (not AUTHORITY)
        $notification = $this->notificationService->prepareNotification(
            incident: $incident,
            actor: $this->superadmin,
            type: BreachNotificationType::DATA_SUBJECT,
            recipient: 'subject@example.com',
        );

        $notification = $this->notificationService->markPreparing($notification, $this->superadmin);
        $notification = $this->notificationService->markReady($notification, $this->superadmin);
        $notification = $this->notificationService->sendNotification($notification, $this->superadmin);

        $this->assertSame(BreachNotificationStatus::SENT, $notification->status);

        // Timer must still be RUNNING — only AUTHORITY satisfies the timer
        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION->value,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $incident->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
    }

    public function test_expired_breach_timer_escalated_by_enforce_command(): void
    {
        $incident = $this->incidentService->createIncident(
            actor: $this->superadmin,
            title: 'Major breach',
            description: 'Large scale exposure',
        );

        $incident = $this->incidentService->qualifyBreach(
            incident: $incident,
            actor: $this->superadmin,
            qualification: BreachQualificationStatus::QUALIFIED,
        );

        // Create an EXPIRED breach notification timer (deadline in the past)
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $incident->id,
            'started_at' => now()->subHours(73),
            'deadline_at' => now()->subHour(),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        // Run the enforce command
        $this->artisan('timers:enforce-statutory')->assertExitCode(0);

        // Expired breach timer should be ESCALATED (not VIOLATED)
        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION->value,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $incident->id,
            'status' => StatutoryTimerStatus::ESCALATED->value,
            'violation_state' => 'ESCALATED',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TIMER_ESCALATED->value,
            'entity_type' => 'StatutoryTimer',
        ]);
    }

    public function test_no_running_timer_means_notification_send_succeeds_without_timer_change(): void
    {
        $incident = $this->incidentService->createIncident(
            actor: $this->superadmin,
            title: 'Incident without timer',
            description: 'No timer created',
        );

        $incident = $this->incidentService->qualifyBreach(
            incident: $incident,
            actor: $this->superadmin,
            qualification: BreachQualificationStatus::QUALIFIED,
        );

        // No timer created — test the case where the listener finds no timer
        $notification = $this->notificationService->prepareNotification(
            incident: $incident,
            actor: $this->superadmin,
            type: BreachNotificationType::AUTHORITY,
            recipient: 'authority@example.com',
        );

        $notification = $this->notificationService->markPreparing($notification, $this->superadmin);
        $notification = $this->notificationService->markReady($notification, $this->superadmin);

        // Send should succeed even without a timer
        $notification = $this->notificationService->sendNotification($notification, $this->superadmin);

        $this->assertSame(BreachNotificationStatus::SENT, $notification->status);

        // No timer should exist
        $timerCount = StatutoryTimer::where('ref_type', IncidentRegister::class)
            ->where('ref_id', $incident->id)
            ->count();
        $this->assertSame(0, $timerCount);
    }

    public function test_duplicate_notification_type_rejected_before_timer_interaction(): void
    {
        $incident = $this->incidentService->createIncident(
            actor: $this->superadmin,
            title: 'Breach with existing notification',
            description: 'Test duplicate prevention',
        );

        $incident = $this->incidentService->qualifyBreach(
            incident: $incident,
            actor: $this->superadmin,
            qualification: BreachQualificationStatus::QUALIFIED,
        );

        // Create first AUTHORITY notification
        $this->notificationService->prepareNotification(
            incident: $incident,
            actor: $this->superadmin,
            type: BreachNotificationType::AUTHORITY,
            recipient: 'authority@example.com',
        );

        // Attempt duplicate should throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Notification type AUTHORITY already exists for this incident');

        $this->notificationService->prepareNotification(
            incident: $incident,
            actor: $this->superadmin,
            type: BreachNotificationType::AUTHORITY,
            recipient: 'authority@example.com',
        );
    }
}
