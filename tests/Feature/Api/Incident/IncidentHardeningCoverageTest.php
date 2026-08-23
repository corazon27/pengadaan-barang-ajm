<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Incident;

use App\Enums\AuditAction;
use App\Enums\BreachNotificationStatus;
use App\Enums\BreachNotificationType;
use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentStatus;
use App\Models\BreachNotification;
use App\Models\IncidentRegister;
use App\Models\User;
use App\Services\BreachNotificationService;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentHardeningCoverageTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->superadmin()->create();
    }

    public function test_classify_without_incident_type_returns_422(): void
    {
        $incident = IncidentRegister::create([
            'title' => 'Test incident',
            'description' => 'Test',
            'status' => IncidentStatus::DETECTED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $this->superadmin->id,
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/classify", [
            'severity' => 'HIGH',
        ])->assertStatus(422);
    }

    public function test_classify_without_severity_returns_422(): void
    {
        $incident = IncidentRegister::create([
            'title' => 'Test incident',
            'description' => 'Test',
            'status' => IncidentStatus::DETECTED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $this->superadmin->id,
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/classify", [
            'incident_type' => 'SECURITY_INCIDENT',
        ])->assertStatus(422);
    }

    public function test_classify_with_invalid_incident_type_returns_422(): void
    {
        $incident = IncidentRegister::create([
            'title' => 'Test incident',
            'description' => 'Test',
            'status' => IncidentStatus::DETECTED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $this->superadmin->id,
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/classify", [
            'incident_type' => 'INVALID_TYPE',
            'severity' => 'HIGH',
        ])->assertStatus(422);
    }

    public function test_breach_notification_failed_audit_logged(): void
    {
        $incident = IncidentRegister::create([
            'title' => 'Breach incident',
            'description' => 'Test',
            'status' => IncidentStatus::CONFIRMED,
            'breach_qualification_status' => BreachQualificationStatus::QUALIFIED,
            'breach_qualified_at' => now(),
            'actor_user_id' => $this->superadmin->id,
        ]);

        $notification = BreachNotification::create([
            'incident_id' => $incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::SENT,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->superadmin);

        $this->app->make(BreachNotificationService::class)
            ->markFailed($notification, $this->superadmin, 'SMTP connection refused');

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::BREACH_NOTIFICATION_FAILED->value,
            'entity_id' => $notification->id,
            'entity_type' => 'BreachNotification',
        ]);
    }

    public function test_breach_notification_acknowledged_audit_logged(): void
    {
        $incident = IncidentRegister::create([
            'title' => 'Breach incident',
            'description' => 'Test',
            'status' => IncidentStatus::CONFIRMED,
            'breach_qualification_status' => BreachQualificationStatus::QUALIFIED,
            'breach_qualified_at' => now(),
            'actor_user_id' => $this->superadmin->id,
        ]);

        $notification = BreachNotification::create([
            'incident_id' => $incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::SENT,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/notifications/{$notification->id}/acknowledge")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::BREACH_NOTIFICATION_ACKNOWLEDGED->value,
            'entity_id' => $notification->id,
            'entity_type' => 'BreachNotification',
        ]);
    }

    public function test_incident_review_opened_creates_human_review_case(): void
    {
        $incident = IncidentRegister::create([
            'title' => 'Ambiguous incident',
            'description' => 'Unclear PII involvement',
            'status' => IncidentStatus::CLASSIFIED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $this->superadmin->id,
        ]);

        $result = $this->app->make(IncidentService::class)
            ->openReview(
                incident: $incident,
                actor: $this->superadmin,
                trigger: 'PSE_REGISTRATION_MISMATCH',
                ruleId: 'PSE-001',
            );

        $this->assertDatabaseHas('human_review_cases', [
            'type' => 'PDP',
            'rule_id' => 'PSE-001',
            'trigger' => 'PSE_REGISTRATION_MISMATCH',
            'subject_type' => 'IncidentRegister',
            'subject_id' => $incident->id,
            'status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('incident_registers', [
            'id' => $incident->id,
            'status' => IncidentStatus::REVIEW_REQUIRED->value,
            'breach_qualification_status' => BreachQualificationStatus::REQUIRE_REVIEW->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_REVIEW_OPENED->value,
            'entity_id' => $incident->id,
            'entity_type' => 'IncidentRegister',
        ]);

        $this->assertNotNull($result->human_review_case_id);
    }
}
