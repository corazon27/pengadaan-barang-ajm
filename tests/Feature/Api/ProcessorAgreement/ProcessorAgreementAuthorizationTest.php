<?php

declare(strict_types=1);

namespace Tests\Feature\Api\ProcessorAgreement;

use App\Models\ProcessorAgreement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessorAgreementAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_processor_agreements(): void
    {
        $this->getJson('/api/v1/processor-agreements')->assertStatus(401);
    }

    public function test_buyer_b2b_cannot_create_processor_agreement(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/processor-agreements', [
            'counterparty_name' => 'Test Corp',
        ])->assertForbidden();
    }

    public function test_buyer_b2g_cannot_view_processor_agreements(): void
    {
        $user = User::factory()->buyerB2g()->create();
        $this->actingAs($user);

        $this->getJson('/api/v1/processor-agreements')->assertForbidden();
    }

    public function test_buyer_b2b_cannot_verify_evidence(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $agreement = ProcessorAgreement::create([
            'counterparty_name' => 'Test Corp',
            'actor_user_id' => $superadmin->id,
        ]);

        $buyer = User::factory()->buyerB2b()->create();
        $this->actingAs($buyer);

        $this->patchJson("/api/v1/processor-agreements/{$agreement->id}/verify-evidence", [
            'factual_basis' => 'Document review',
        ])->assertForbidden();
    }

    public function test_superadmin_can_access_all_endpoints(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);

        $this->getJson('/api/v1/processor-agreements')->assertOk();

        $this->postJson('/api/v1/processor-agreements', [
            'counterparty_name' => 'Test Corp',
        ])->assertCreated();
    }
}
