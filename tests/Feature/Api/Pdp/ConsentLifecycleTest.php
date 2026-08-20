<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Pdp;

use App\Enums\AuditAction;
use App\Enums\ConsentStatus;
use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_grant_consent(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $subject = User::factory()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/consent', [
            'subject_user_id' => $subject->id,
            'purpose' => 'marketing_communications',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v2.1',
            'document_ref' => 'privacy_policy_2026',
            'source_channel' => 'WEB',
        ])
            ->assertCreated()
            ->assertJsonPath('data.consent_status', ConsentStatus::ACTIVE->value)
            ->assertJsonPath('data.purpose', 'marketing_communications');

        $this->assertDatabaseHas('consent_records', [
            'purpose' => 'marketing_communications',
            'consent_status' => ConsentStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CONSENT_GRANTED->value,
            'entity_type' => 'ConsentRecord',
        ]);
    }

    public function test_superadmin_can_list_consents(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $subject = User::factory()->create();

        ConsentRecord::create([
            'subject_user_id' => $subject->id,
            'subject_type' => 'USER',
            'purpose' => 'analytics',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
            'granted_at' => now(),
        ]);

        $this->actingAs($superadmin);

        $this->getJson('/api/v1/consent')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_subject_can_view_own_consent(): void
    {
        $user = User::factory()->create();

        $record = ConsentRecord::create([
            'subject_user_id' => $user->id,
            'subject_type' => 'USER',
            'purpose' => 'analytics',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
            'granted_at' => now(),
        ]);

        $this->actingAs($user);

        $this->getJson("/api/v1/consent/{$record->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $record->id);
    }

    public function test_regular_user_cannot_view_other_consent(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $record = ConsentRecord::create([
            'subject_user_id' => $other->id,
            'subject_type' => 'USER',
            'purpose' => 'analytics',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
            'granted_at' => now(),
        ]);

        $this->actingAs($user);

        $this->getJson("/api/v1/consent/{$record->id}")
            ->assertForbidden();
    }

    public function test_subject_can_withdraw_own_consent(): void
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
        ]);

        $this->actingAs($user);

        $this->patchJson("/api/v1/consent/{$record->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.consent_status', ConsentStatus::WITHDRAWN->value);

        $this->assertDatabaseHas('consent_records', [
            'id' => $record->id,
            'consent_status' => ConsentStatus::WITHDRAWN->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CONSENT_WITHDRAWN->value,
            'entity_type' => 'ConsentRecord',
        ]);
    }

    public function test_superadmin_can_supersede_consent(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $subject = User::factory()->create();

        $old = ConsentRecord::create([
            'subject_user_id' => $subject->id,
            'subject_type' => 'USER',
            'purpose' => 'marketing',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2025',
            'source_channel' => 'WEB',
            'granted_at' => now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/consent/{$old->id}/supersede", [
            'notice_version' => 'v2.0',
            'document_ref' => 'policy_2026',
        ])
            ->assertOk()
            ->assertJsonPath('data.consent_status', ConsentStatus::ACTIVE->value)
            ->assertJsonPath('data.notice_version', 'v2.0');

        $this->assertDatabaseHas('consent_records', [
            'id' => $old->id,
            'consent_status' => ConsentStatus::SUPERSEDED->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CONSENT_SUPERSEDED->value,
            'entity_type' => 'ConsentRecord',
        ]);
    }

    public function test_superadmin_can_invalidate_consent(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $subject = User::factory()->create();

        $record = ConsentRecord::create([
            'subject_user_id' => $subject->id,
            'subject_type' => 'USER',
            'purpose' => 'analytics',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
            'granted_at' => now(),
        ]);

        $this->actingAs($superadmin);

        $this->patchJson("/api/v1/consent/{$record->id}/invalidate", [
            'reason' => 'Missing document evidence',
        ])
            ->assertOk()
            ->assertJsonPath('data.consent_status', ConsentStatus::INVALID->value);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::CONSENT_INVALIDATED->value,
            'entity_type' => 'ConsentRecord',
        ]);
    }

    public function test_consent_store_validates_required_fields(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $this->actingAs($superadmin);

        $this->postJson('/api/v1/consent', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'subject_user_id',
                'purpose',
                'processing_lawful_basis',
                'notice_version',
                'document_ref',
                'source_channel',
            ]);
    }

    public function test_regular_user_cannot_grant_consent(): void
    {
        $user = User::factory()->create();
        $subject = User::factory()->create();

        $this->actingAs($user);

        $this->postJson('/api/v1/consent', [
            'subject_user_id' => $subject->id,
            'purpose' => 'marketing',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
        ])
            ->assertForbidden();
    }
}
