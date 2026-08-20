<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Incident;

use App\Enums\AuditAction;
use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Events\BreachQualifiedEvent;
use App\Models\IncidentRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class IncidentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_incident(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/incidents', [
            'title' => 'Unauthorized access to customer database',
            'description' => 'Suspicious activity detected on production database server',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', IncidentStatus::DETECTED->value)
            ->assertJsonPath('data.breach_qualification_status', BreachQualificationStatus::UNKNOWN->value);

        $this->assertDatabaseHas('incident_registers', [
            'title' => 'Unauthorized access to customer database',
            'status' => IncidentStatus::DETECTED->value,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_CREATED->value,
            'entity_type' => 'IncidentRegister',
        ]);
    }

    public function test_superadmin_can_classify_incident(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $incident = IncidentRegister::create([
            'title' => 'Test incident',
            'description' => 'Test description',
            'status' => IncidentStatus::DETECTED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/classify", [
            'incident_type' => 'SECURITY_INCIDENT',
            'severity' => 'HIGH',
        ])
            ->assertOk()
            ->assertJsonPath('data.incident_type', IncidentType::SECURITY_INCIDENT->value)
            ->assertJsonPath('data.severity', IncidentSeverity::HIGH->value)
            ->assertJsonPath('data.status', IncidentStatus::CLASSIFIED->value);

        $this->assertDatabaseHas('incident_registers', [
            'id' => $incident->id,
            'incident_type' => IncidentType::SECURITY_INCIDENT->value,
            'severity' => IncidentSeverity::HIGH->value,
            'status' => IncidentStatus::CLASSIFIED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_CLASSIFIED->value,
            'entity_id' => $incident->id,
        ]);
    }

    public function test_qualify_breach_qualified_emits_event(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        Event::fake([BreachQualifiedEvent::class]);

        $incident = IncidentRegister::create([
            'title' => 'Data breach detected',
            'description' => 'Customer PII exposed',
            'status' => IncidentStatus::CLASSIFIED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/qualify-breach", [
            'breach_qualification_status' => 'QUALIFIED',
        ])
            ->assertOk()
            ->assertJsonPath('data.breach_qualification_status', BreachQualificationStatus::QUALIFIED->value)
            ->assertJsonPath('data.status', IncidentStatus::CONFIRMED->value);

        Event::assertDispatched(BreachQualifiedEvent::class, function ($event) use ($incident) {
            return $event->sourceType === IncidentRegister::class
                && $event->sourceId === $incident->id
                && $event->ruleId === 'PDP-BREACH-001';
        });

        $this->assertDatabaseHas('incident_registers', [
            'id' => $incident->id,
            'breach_qualification_status' => BreachQualificationStatus::QUALIFIED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_BREACH_QUALIFIED->value,
            'entity_id' => $incident->id,
        ]);
    }

    public function test_qualify_breach_not_qualified_does_not_emit_event(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        Event::fake([BreachQualifiedEvent::class]);

        $incident = IncidentRegister::create([
            'title' => 'Suspicious activity investigated',
            'description' => 'No personal data found',
            'status' => IncidentStatus::CLASSIFIED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/qualify-breach", [
            'breach_qualification_status' => 'NOT_QUALIFIED',
        ])
            ->assertOk()
            ->assertJsonPath('data.breach_qualification_status', BreachQualificationStatus::NOT_QUALIFIED->value)
            ->assertJsonPath('data.status', IncidentStatus::CONFIRMED->value);

        Event::assertNotDispatched(BreachQualifiedEvent::class);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_BREACH_NOT_QUALIFIED->value,
            'entity_id' => $incident->id,
        ]);
    }

    public function test_qualify_breach_require_review_sets_review_required(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $incident = IncidentRegister::create([
            'title' => 'Ambiguous incident',
            'description' => 'Unclear if PII involved',
            'status' => IncidentStatus::CLASSIFIED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/qualify-breach", [
            'breach_qualification_status' => 'REQUIRE_REVIEW',
        ])
            ->assertOk()
            ->assertJsonPath('data.breach_qualification_status', BreachQualificationStatus::REQUIRE_REVIEW->value)
            ->assertJsonPath('data.status', IncidentStatus::REVIEW_REQUIRED->value);
    }

    public function test_invalid_qualification_transition_rejected(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $incident = IncidentRegister::create([
            'title' => 'Already qualified incident',
            'description' => 'Test',
            'status' => IncidentStatus::CONFIRMED,
            'breach_qualification_status' => BreachQualificationStatus::QUALIFIED,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/qualify-breach", [
            'breach_qualification_status' => 'NOT_QUALIFIED',
        ])->assertStatus(422);
    }

    public function test_severity_does_not_determine_breach_qualification(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        Event::fake([BreachQualifiedEvent::class]);

        $incident = IncidentRegister::create([
            'title' => 'Critical PSE disruption',
            'description' => 'System outage',
            'status' => IncidentStatus::CLASSIFIED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/qualify-breach", [
            'breach_qualification_status' => 'NOT_QUALIFIED',
        ])->assertOk();

        Event::assertNotDispatched(BreachQualifiedEvent::class);

        $this->assertDatabaseHas('incident_registers', [
            'id' => $incident->id,
            'breach_qualification_status' => BreachQualificationStatus::NOT_QUALIFIED->value,
        ]);
    }

    public function test_system_confirmation_does_not_imply_legal_confirmation(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $incident = IncidentRegister::create([
            'title' => 'Incident under investigation',
            'description' => 'No breach found',
            'status' => IncidentStatus::CLASSIFIED,
            'breach_qualification_status' => BreachQualificationStatus::UNKNOWN,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/qualify-breach", [
            'breach_qualification_status' => 'NOT_QUALIFIED',
        ])->assertOk();

        $incident->refresh();

        $this->assertEquals(IncidentStatus::CONFIRMED->value, $incident->status->value);
        $this->assertEquals(BreachQualificationStatus::NOT_QUALIFIED->value, $incident->breach_qualification_status->value);
        $this->assertNull($incident->breach_qualified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_BREACH_NOT_QUALIFIED->value,
            'entity_id' => $incident->id,
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::INCIDENT_BREACH_QUALIFIED->value,
            'entity_id' => $incident->id,
        ]);
    }

    public function test_unauthorized_user_cannot_create_incident(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/incidents', [
            'title' => 'Test',
            'description' => 'Test',
        ])->assertForbidden();
    }

    public function test_incident_can_be_resolved_and_closed(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $incident = IncidentRegister::create([
            'title' => 'Resolved incident',
            'description' => 'Contained',
            'status' => IncidentStatus::CONFIRMED,
            'breach_qualification_status' => BreachQualificationStatus::QUALIFIED,
            'actor_user_id' => $superadmin->id,
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/incidents/{$incident->id}/resolve", [
            'containment_status' => 'CONTAINED',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', IncidentStatus::RESOLVED->value);

        $this->patchJson("/api/v1/incidents/{$incident->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', IncidentStatus::CLOSED->value);

        $this->assertDatabaseHas('incident_registers', [
            'id' => $incident->id,
            'status' => IncidentStatus::CLOSED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_RESOLVED->value,
            'entity_id' => $incident->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::INCIDENT_CLOSED->value,
            'entity_id' => $incident->id,
        ]);
    }
}
