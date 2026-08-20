<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Incident;

use App\Enums\AuditAction;
use App\Enums\BreachNotificationStatus;
use App\Enums\BreachNotificationType;
use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentStatus;
use App\Events\BreachNotificationSent;
use App\Models\BreachNotification;
use App\Models\IncidentRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BreachNotificationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private IncidentRegister $incident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->superadmin()->create();

        $this->incident = IncidentRegister::create([
            'title' => 'Data breach confirmed',
            'description' => 'Customer PII exposed in unauthorized access',
            'status' => IncidentStatus::CONFIRMED,
            'breach_qualification_status' => BreachQualificationStatus::QUALIFIED,
            'breach_qualified_at' => now(),
            'actor_user_id' => $this->superadmin->id,
        ]);
    }

    public function test_prepare_notification_creates_pending(): void
    {
        $this->actingAs($this->superadmin);

        $this->postJson("/api/v1/incidents/{$this->incident->id}/notifications", [
            'notification_type' => 'AUTHORITY',
            'recipient' => 'dataprotection@authority.go.id',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', BreachNotificationStatus::PENDING->value)
            ->assertJsonPath('data.notification_type', BreachNotificationType::AUTHORITY->value);

        $this->assertDatabaseHas('breach_notifications', [
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY->value,
            'status' => BreachNotificationStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::BREACH_NOTIFICATION_PREPARED->value,
            'entity_type' => 'BreachNotification',
        ]);
    }

    public function test_pending_to_sent_directly_is_rejected(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::PENDING,
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/send")
            ->assertStatus(422);
    }

    public function test_preparing_to_sent_directly_is_rejected(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::PREPARING,
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/send")
            ->assertStatus(422);
    }

    public function test_valid_ready_to_sent_succeeds(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::READY,
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', BreachNotificationStatus::SENT->value);

        $this->assertDatabaseHas('breach_notifications', [
            'id' => $notification->id,
            'status' => BreachNotificationStatus::SENT->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::BREACH_NOTIFICATION_SENT->value,
            'entity_id' => $notification->id,
        ]);
    }

    public function test_authority_notification_sends_event(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::READY,
        ]);

        Event::fake([BreachNotificationSent::class]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/send")
            ->assertOk();

        Event::assertDispatched(BreachNotificationSent::class, function ($event) use ($notification) {
            return $event->notification->id === $notification->id
                && $event->incident->id === $this->incident->id;
        });
    }

    public function test_data_subject_notification_does_not_send_event(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::DATA_SUBJECT,
            'recipient' => 'subject@example.com',
            'status' => BreachNotificationStatus::READY,
        ]);

        Event::fake([BreachNotificationSent::class]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/send")
            ->assertOk();

        Event::assertNotDispatched(BreachNotificationSent::class);
    }

    public function test_supervisor_notification_does_not_send_event(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::SUPERVISOR,
            'recipient' => 'supervisor@example.com',
            'status' => BreachNotificationStatus::READY,
        ]);

        Event::fake([BreachNotificationSent::class]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/send")
            ->assertOk();

        Event::assertNotDispatched(BreachNotificationSent::class);
    }

    public function test_duplicate_notification_type_per_incident_blocked(): void
    {
        BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::SENT,
        ]);

        $this->actingAs($this->superadmin);

        $this->postJson("/api/v1/incidents/{$this->incident->id}/notifications", [
            'notification_type' => 'AUTHORITY',
            'recipient' => 'another@example.com',
        ])->assertStatus(422);
    }

    public function test_cancel_notification_from_pending_succeeds(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::SUPERVISOR,
            'recipient' => 'supervisor@example.com',
            'status' => BreachNotificationStatus::PENDING,
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', BreachNotificationStatus::CANCELLED->value);

        $this->assertDatabaseHas('breach_notifications', [
            'id' => $notification->id,
            'status' => BreachNotificationStatus::CANCELLED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::BREACH_NOTIFICATION_CANCELLED->value,
            'entity_id' => $notification->id,
        ]);
    }

    public function test_unauthorized_user_cannot_prepare_notification(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->actingAs($user);

        $this->postJson("/api/v1/incidents/{$this->incident->id}/notifications", [
            'notification_type' => 'AUTHORITY',
            'recipient' => 'authority@example.com',
        ])->assertForbidden();
    }
}
