<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Pdp;

use App\Enums\ConsentStatus;
use App\Enums\DsrStatus;
use App\Enums\StatutoryTimerEnforcement;
use App\Enums\StatutoryTimerStatus;
use App\Enums\StatutoryTimerType;
use App\Models\ConsentRecord;
use App\Models\DataSubjectRequest;
use App\Models\StatutoryTimer;
use App\Models\User;
use App\Services\ConsentService;
use App\Services\DataSubjectRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentWithdrawalTimerWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_withdrawal_creates_timer_in_same_transaction(): void
    {
        $user = User::factory()->create();
        $record = ConsentRecord::create([
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

        $consentService = app(ConsentService::class);
        $result = $consentService->withdraw($record, $user);

        $this->assertSame(ConsentStatus::WITHDRAWN, $result->consent_status);
        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::CONSENT_WITHDRAWAL->value,
            'ref_type' => ConsentRecord::class,
            'ref_id' => $record->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
    }

    public function test_restriction_acceptance_creates_timer(): void
    {
        $user = User::factory()->create();
        $dsr = DataSubjectRequest::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-007',
            'channel' => 'EMAIL',
            'status' => DsrStatus::RECEIVED,
            'applicability_status' => 'APPLICABILITY_UNKNOWN',
        ]);

        $dsrService = app(DataSubjectRequestService::class);
        $result = $dsrService->acceptRestriction($dsr, $user);

        $this->assertSame(DsrStatus::PROCESSING, $result->status);
        $this->assertDatabaseHas('statutory_timers', [
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION->value,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'status' => StatutoryTimerStatus::RUNNING->value,
        ]);
    }

    public function test_fulfill_does_not_mark_restriction_timer_met(): void
    {
        $user = User::factory()->create();
        $dsr = DataSubjectRequest::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-007',
            'channel' => 'EMAIL',
            'status' => DsrStatus::RECEIVED,
            'applicability_status' => 'APPLICABILITY_UNKNOWN',
        ]);

        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION,
            'enforcement' => StatutoryTimerEnforcement::SUSPEND_RESTRICT_PROCESSING,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $dsrService = app(DataSubjectRequestService::class);
        $dsrService->fulfill($dsr, $user, 'administrative closure');

        $timer = StatutoryTimer::where('ref_type', DataSubjectRequest::class)
            ->where('ref_id', $dsr->id)
            ->first();
        $this->assertSame(StatutoryTimerStatus::RUNNING, $timer->status);
    }

    public function test_confirm_restriction_completion_marks_timer_met(): void
    {
        $user = User::factory()->create();
        $dsr = DataSubjectRequest::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'right_code' => 'PDP-RIGHT-007',
            'channel' => 'EMAIL',
            'status' => DsrStatus::PROCESSING,
            'applicability_status' => 'APPLICABILITY_UNKNOWN',
        ]);

        StatutoryTimer::create([
            'timer_type' => StatutoryTimerType::RESTRICTION_SUSPENSION,
            'enforcement' => StatutoryTimerEnforcement::SUSPEND_RESTRICT_PROCESSING,
            'ref_type' => DataSubjectRequest::class,
            'ref_id' => $dsr->id,
            'started_at' => now(),
            'deadline_at' => now()->addHours(72),
            'status' => StatutoryTimerStatus::RUNNING,
        ]);

        $dsrService = app(DataSubjectRequestService::class);
        $result = $dsrService->confirmRestrictionCompletion($dsr, $user, 'restriction applied');

        $this->assertSame(DsrStatus::FULFILLED, $result->status);
        $timer = StatutoryTimer::where('ref_type', DataSubjectRequest::class)
            ->where('ref_id', $dsr->id)
            ->first();
        $this->assertSame(StatutoryTimerStatus::MET, $timer->status);
    }
}
