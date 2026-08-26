<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Authorization;

use App\Models\ConsentRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsentIdorTest extends TestCase
{
    use RefreshDatabase;

    public function test_unrelated_user_cannot_withdraw_others_consent(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $record = ConsentRecord::create([
            'subject_user_id' => $other->id,
            'subject_type' => 'USER',
            'purpose' => 'marketing',
            'processing_lawful_basis' => 'CONSENT',
            'notice_version' => 'v1.0',
            'document_ref' => 'policy_2026',
            'source_channel' => 'WEB',
            'granted_at' => now(),
            'consent_status' => 'ACTIVE',
        ]);

        $this->actingAs($user);

        $this->patchJson("/api/v1/consent/{$record->id}/withdraw")
            ->assertForbidden();

        $this->assertDatabaseHas('consent_records', [
            'id' => $record->id,
            'consent_status' => 'ACTIVE',
        ]);
    }

    public function test_unauthenticated_user_cannot_withdraw_consent(): void
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
            'consent_status' => 'ACTIVE',
        ]);

        $this->patchJson("/api/v1/consent/{$record->id}/withdraw")
            ->assertUnauthorized();
    }
}
