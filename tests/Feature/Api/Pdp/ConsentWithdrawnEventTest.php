<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Pdp;

use App\Enums\ConsentStatus;
use App\Events\ConsentWithdrawn;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\ConsentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ConsentWithdrawnEventTest extends TestCase
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

    public function test_successful_withdrawal_dispatches_exactly_one_event(): void
    {
        Event::fake([ConsentWithdrawn::class]);

        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $result = $service->withdraw($record, $user);

        $this->assertSame(ConsentStatus::WITHDRAWN, $result->consent_status);
        Event::assertDispatchedOnce(ConsentWithdrawn::class);
        Event::assertDispatched(
            ConsentWithdrawn::class,
            fn (ConsentWithdrawn $event) => $event->consent->id === $record->id,
        );
    }

    public function test_event_payload_references_correct_consent_record(): void
    {
        Event::fake([ConsentWithdrawn::class]);

        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $service->withdraw($record, $user);

        Event::assertDispatched(
            ConsentWithdrawn::class,
            fn (ConsentWithdrawn $event) => $event->consent instanceof ConsentRecord
                && $event->consent->id === $record->id,
        );
    }

    public function test_event_carries_withdrawn_state(): void
    {
        Event::fake([ConsentWithdrawn::class]);

        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $service->withdraw($record, $user);

        Event::assertDispatched(
            ConsentWithdrawn::class,
            fn (ConsentWithdrawn $event) => $event->consent->consent_status === ConsentStatus::WITHDRAWN,
        );
    }

    public function test_second_withdrawal_rejected_total_dispatch_count_remains_one(): void
    {
        Event::fake([ConsentWithdrawn::class]);

        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        $service->withdraw($record->fresh(), $user);

        try {
            $service->withdraw($record->fresh(), $user);
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('WITHDRAWN', $e->getMessage());
        }

        Event::assertDispatchedOnce(ConsentWithdrawn::class);
    }

    public function test_rollback_prevents_event_dispatch(): void
    {
        Event::fake([ConsentWithdrawn::class]);

        $record = $this->createActiveConsent();
        $user = User::find($record->subject_user_id);
        $service = app(ConsentService::class);

        DB::beginTransaction();

        try {
            $service->withdraw($record, $user);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        DB::rollBack();

        Event::assertNotDispatched(ConsentWithdrawn::class);
        $this->assertDatabaseHas('consent_records', [
            'id' => $record->id,
            'consent_status' => ConsentStatus::ACTIVE,
        ]);
        $this->assertDatabaseCount('statutory_timers', 0);
    }
}
