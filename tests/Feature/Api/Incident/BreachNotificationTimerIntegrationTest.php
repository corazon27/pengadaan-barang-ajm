<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Incident;

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
use App\Services\StatutoryTimerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreachNotificationTimerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    private IncidentRegister $incident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->superadmin()->create();

        $this->incident = IncidentRegister::create([
            'title' => 'Breach confirmed',
            'description' => 'PII exposed',
            'status' => IncidentStatus::CONFIRMED,
            'breach_qualification_status' => BreachQualificationStatus::QUALIFIED,
            'breach_qualified_at' => now(),
            'actor_user_id' => $this->superadmin->id,
        ]);

        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::BREACH_NOTIFICATION,
            'enforcement' => StatutoryTimerEnforcement::ESCALATION_VIOLATION_AUDIT,
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'started_at' => now()->subHours(24),
            'deadline_at' => now()->addHours(48),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);
    }

    public function test_authority_notification_satisfies_timer(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::READY,
        ]);

        $listener = new SignalBreachNotificationSent($this->app->make(StatutoryTimerService::class));

        $event = new BreachNotificationSent($notification, $this->incident);

        $listener->handle($event);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'status' => StatutoryTimerStatus::MET->value,
        ]);
    }

    public function test_data_subject_notification_does_not_satisfy_timer(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::DATA_SUBJECT,
            'recipient' => 'subject@example.com',
            'status' => BreachNotificationStatus::READY,
        ]);

        $listener = new SignalBreachNotificationSent($this->app->make(StatutoryTimerService::class));

        $event = new BreachNotificationSent($notification, $this->incident);

        $listener->handle($event);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
    }

    public function test_supervisor_notification_does_not_satisfy_timer(): void
    {
        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::SUPERVISOR,
            'recipient' => 'supervisor@example.com',
            'status' => BreachNotificationStatus::READY,
        ]);

        $listener = new SignalBreachNotificationSent($this->app->make(StatutoryTimerService::class));

        $event = new BreachNotificationSent($notification, $this->incident);

        $listener->handle($event);

        $this->assertDatabaseHas('statutory_timers', [
            'ref_type' => IncidentRegister::class,
            'ref_id' => $this->incident->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
    }

    public function test_already_escalated_timer_not_rewound_to_met(): void
    {
        $timer = StatutoryTimer::where('ref_type', IncidentRegister::class)
            ->where('ref_id', $this->incident->id)
            ->first();

        $timer->update(['status' => StatutoryTimerStatus::ESCALATED]);

        $notification = BreachNotification::create([
            'incident_id' => $this->incident->id,
            'notification_type' => BreachNotificationType::AUTHORITY,
            'recipient' => 'authority@example.com',
            'status' => BreachNotificationStatus::READY,
        ]);

        $listener = new SignalBreachNotificationSent($this->app->make(StatutoryTimerService::class));

        $event = new BreachNotificationSent($notification, $this->incident);

        $listener->handle($event);

        $this->assertDatabaseHas('statutory_timers', [
            'id' => $timer->id,
            'status' => StatutoryTimerStatus::ESCALATED->value,
        ]);
    }
}
