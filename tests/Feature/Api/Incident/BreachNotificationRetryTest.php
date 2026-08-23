<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Incident;

use App\Enums\AuditAction;
use App\Enums\BreachNotificationStatus;
use App\Enums\BreachNotificationType;
use App\Enums\BreachQualificationStatus;
use App\Enums\IncidentStatus;
use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Events\BreachNotificationSent;
use App\Listeners\SignalBreachNotificationSent;
use App\Models\BreachNotification;
use App\Models\IncidentRegister;
use App\Models\StatutoryTimer;
use App\Models\User;
use App\Services\BreachNotificationService;
use App\Services\StatutoryTimerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreachNotificationRetryTest extends TestCase
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
            'description' => 'Customer PII exposed',
            'status' => IncidentStatus::CONFIRMED,
            'breach_qualification_status' => BreachQualificationStatus::QUALIFIED,
            'breach_qualified_at' => now(),
            'actor_user_id' => $this->superadmin->id,
        ]);
    }

    public function test_failed_notification_can_be_retried(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Connection timeout',
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', BreachNotificationStatus::PREPARING->value)
            ->assertJsonPath('data.retry_count', 1);

        $this->assertDatabaseHas('breach_notifications', [
            'id' => $notification->id,
            'status' => BreachNotificationStatus::PREPARING->value,
            'retry_count' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::BREACH_NOTIFICATION_RETRY->value,
            'entity_id' => $notification->id,
        ]);
    }

    public function test_retry_reuses_same_record(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Temporary error',
        ]);

        $originalId = $notification->id;

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk();

        $count = BreachNotification::where('incident_id', $this->incident->id)
            ->where('notification_type', BreachNotificationType::AUTHORITY)
            ->count();

        $this->assertEquals(1, $count);

        $this->assertDatabaseHas('breach_notifications', [
            'id' => $originalId,
            'status' => BreachNotificationStatus::PREPARING->value,
            'retry_count' => 1,
        ]);
    }

    public function test_retry_increments_retry_count(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Error 1',
            'retry_count' => 2,
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.retry_count', 3);

        $this->assertDatabaseHas('breach_notifications', [
            'id' => $notification->id,
            'retry_count' => 3,
        ]);

        $notification->refresh();
        $this->assertNotNull($notification->last_retry_at);
    }

    public function test_concurrent_retry_is_idempotent(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Error',
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk();

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertStatus(422);

        $this->assertDatabaseHas('breach_notifications', [
            'id' => $notification->id,
            'status' => BreachNotificationStatus::PREPARING->value,
            'retry_count' => 1,
        ]);

        $count = BreachNotification::where('id', $notification->id)->count();
        $this->assertEquals(1, $count);
    }

    public function test_authority_retry_then_send_satisfies_timer(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'started_at' => now()->subHours(24),
            'deadline_at' => now()->addHours(48),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Error',
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', BreachNotificationStatus::PREPARING->value);

        $notificationService = $this->app->make(BreachNotificationService::class);
        $notification->refresh();
        $notificationService->markReady($notification, $this->superadmin);

        $listener = new SignalBreachNotificationSent($this->app->make(StatutoryTimerService::class));
        $event = new BreachNotificationSent($notification->fresh(), $this->incident);
        $listener->handle($event);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'status' => StatutoryTimerStatus::MET->value,
        ]);
    }

    public function test_data_subject_retry_never_satisfies_timer(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'started_at' => now()->subHours(24),
            'deadline_at' => now()->addHours(48),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::DATA_SUBJECT,
            'recipient' => 'subject@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Error',
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk();

        $notificationService = $this->app->make(BreachNotificationService::class);
        $notification->refresh();
        $notificationService->markReady($notification, $this->superadmin);

        $listener = new SignalBreachNotificationSent($this->app->make(StatutoryTimerService::class));
        $event = new BreachNotificationSent($notification->fresh(), $this->incident);
        $listener->handle($event);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
    }

    public function test_supervisor_retry_never_satisfies_timer(): void
    {
        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'started_at' => now()->subHours(24),
            'deadline_at' => now()->addHours(48),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::SUPERVISOR,
            'recipient' => 'supervisor@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Error',
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk();

        $notificationService = $this->app->make(BreachNotificationService::class);
        $notification->refresh();
        $notificationService->markReady($notification, $this->superadmin);

        $listener = new SignalBreachNotificationSent($this->app->make(StatutoryTimerService::class));
        $event = new BreachNotificationSent($notification->fresh(), $this->incident);
        $listener->handle($event);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
    }

    public function test_already_escalated_timer_not_rewound_by_retry_send(): void
    {
        $timer = StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'started_at' => now()->subHours(100),
            'deadline_at' => now()->subHours(28),
            'status' => StatutoryTimerStatus::ESCALATED,
        ]);

        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Error',
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk();

        $notificationService = $this->app->make(BreachNotificationService::class);
        $notification->refresh();
        $notificationService->markReady($notification, $this->superadmin);

        $listener = new SignalBreachNotificationSent($this->app->make(StatutoryTimerService::class));
        $event = new BreachNotificationSent($notification->fresh(), $this->incident);
        $listener->handle($event);

        $this->assertDatabaseHas('statutory_timers', [
            'id' => $timer->id,
            'status' => StatutoryTimerStatus::ESCALATED->value,
        ]);
    }

    public function test_retry_preserves_failure_history(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Connection timeout',
            'failed_at' => now()->subHours(1),
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk();

        $notification->refresh();

        $this->assertEquals('Connection timeout', $notification->failure_reason);
        $this->assertNotNull($notification->failed_at);
        $this->assertEquals(BreachNotificationStatus::PREPARING->value, $notification->status->value);
    }

    public function test_non_failed_notification_cannot_be_retried(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::SENT,
            'sent_at' => now(),
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertStatus(422);
    }

    public function test_retry_then_full_lifecycle_succeeds(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Error',
        ]);

        $this->actingAs($this->superadmin);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertOk()
            ->assertJsonPath('data.status', BreachNotificationStatus::PREPARING->value);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/send")
            ->assertStatus(422);

        $notification->update(['status' => BreachNotificationStatus::READY]);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/send")
            ->assertOk()
            ->assertJsonPath('data.status', BreachNotificationStatus::SENT->value);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', BreachNotificationStatus::CONFIRMED->value);

        $this->assertDatabaseHas('breach_notifications', [
            'id' => $notification->id,
            'status' => BreachNotificationStatus::CONFIRMED->value,
            'retry_count' => 1,
        ]);
    }

    public function test_unauthorized_user_cannot_retry(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::FAILED,
            'failure_reason' => 'Error',
        ]);

        $this->actingAs($user);

        $this->patchJson("/api/v1/incidents/{$this->incident->id}/notifications/{$notification->id}/retry")
            ->assertForbidden();
    }
}
