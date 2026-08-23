<?php

declare(strict_types=1);

namespace Tests\Feature\Api\LegalFunction;

use App\Enums\LegalFunctionStatus;
use App\Models\LegalFunctionAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalFunctionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_legal_functions(): void
    {
        $this->getJson('/api/v1/legal-functions')->assertStatus(401);
    }

    public function test_buyer_b2b_cannot_create_legal_function(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/legal-functions', [
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'effective_from' => '2026-01-01',
        ])->assertForbidden();
    }

    public function test_buyer_b2g_cannot_view_legal_functions(): void
    {
        $user = User::factory()->buyerB2g()->create();
        $this->actingAs($user);

        $this->getJson('/api/v1/legal-functions')->assertForbidden();
    }

    public function test_buyer_b2b_cannot_activate_legal_function(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $assignment = LegalFunctionAssignment::create([
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'status' => LegalFunctionStatus::INACTIVE,
            'effective_from' => '2026-01-01',
        ]);

        $buyer = User::factory()->buyerB2b()->create();
        $this->actingAs($buyer);

        $this->patchJson("/api/v1/legal-functions/{$assignment->id}/activate")->assertForbidden();
    }

    public function test_superadmin_can_access_all_endpoints(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $this->actingAs($superadmin);

        $this->getJson('/api/v1/legal-functions')->assertOk();

        $this->postJson('/api/v1/legal-functions', [
            'function_code' => 'DPO',
            'function_category' => 'DATA_PROTECTION',
            'effective_from' => '2026-01-01',
        ])->assertCreated();
    }
}
