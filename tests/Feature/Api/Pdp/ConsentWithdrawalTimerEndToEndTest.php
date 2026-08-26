<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Pdp;

use App\Enums\ConsentStatus;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Events\ConsentWithdrawn;
use App\Models\ConsentRecord;
use App\Models\StatutoryTimer;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConsentWithdrawalTimerEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function createActiveConsent(): ConsentRecord
    {
        $user = User::factory()->create();

        return ConsentRecord::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'purpose' => 'marketing',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
            'granted_at' => now(),
            'consent_status' => ConsentStatus::ACTIVE,
        ]);
    }

    public function test_withdrawal_creates_exactly_one_running_timer(): void
    {
        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $service->withdraw($record->fresh(), $user);

        $this->assertDatabaseCount('statutory_timers', 1);
        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL->value,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
    }

    public function test_timer_references_correct_consent_record(): void
    {
        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $service->withdraw($record->fresh(), $user);

        $timer = StatutoryTimer::where('ref_type', ConsentRecord::class)
            ->where('ref_id', $record->id)
            ->first();

        $this->assertNotNull($timer);
        $this->assertSame(StatutoryTimerType::CONSENT_WITHDRAWAL, $timer->timer_type);
        $this->assertSame(StatutoryTimerStatus::RUNNING, $timer->status);
    }

    public function test_timer_deadline_is_72_hours_from_withdrawal(): void
    {
        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $service->withdraw($record->fresh(), $user);

        $consentRow = ConsentRecord::find($record->id);
        $withdrawnAt = $consentRow->withdrawn_at;

        $this->assertNotNull($withdrawnAt);

        $timer = StatutoryTimer::where('ref_type', ConsentRecord::class)
            ->where('ref_id', $record->id)
            ->first();

        $this->assertNotNull($timer);
        $expectedDeadline = $withdrawnAt->copy()->addHours(72);
        $this->assertEqualsWithDelta($expectedDeadline, $timer->deadline_at, 1);
    }

    public function test_withdrawal_deadline_set_on_consent_record_by_listener(): void
    {
        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $service->withdraw($record->fresh(), $user);

        $consentRow = ConsentRecord::find($record->id);
        $this->assertNotNull($consentRow->withdrawal_deadline_at);

        $timer = StatutoryTimer::where('ref_type', ConsentRecord::class)
            ->where('ref_id', $record->id)
            ->first();

        $this->assertNotNull($timer);
        $this->assertEqualsWithDelta(
            $timer->deadline_at,
            $consentRow->withdrawal_deadline_at,
            1,
        );
    }

    public function test_repeated_event_delivery_does_not_create_duplicate_timer(): void
    {
        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $service->withdraw($record->fresh(), $user);

        $this->assertDatabaseCount('statutory_timers', 1);

        event(new ConsentWithdrawn($record->fresh()));

        $this->assertDatabaseCount('statutory_timers', 1);
    }

    public function test_rollback_creates_no_timer(): void
    {
        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        DB::beginTransaction();

        try {
            $service->withdraw($record->fresh(), $user);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        DB::rollBack();

        $this->assertDatabaseCount('statutory_timers', 0);
        $this->assertDatabaseHas('consent_records', [
            'id' => $record->id,
            'consent_status' => ConsentStatus::ACTIVE,
        ]);
    }
}
