<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ResubmitTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejected_applicant_can_resubmit_and_returns_to_pending(): void
    {
        $user = User::factory()->rejected()->create();

        $url = URL::temporarySignedRoute('resubmit.store', now()->addHour(), [
            'user' => $user->getKey(),
        ]);

        $this->post($url, ['requested_role' => UserRole::BUYER_B2G->value])
            ->assertRedirect(route('login'));

        $fresh = $user->fresh();
        $this->assertSame(UserStatus::PENDING, $fresh->status);
        $this->assertSame(UserRole::BUYER_B2G, $fresh->requested_role);
        $this->assertNull($fresh->status_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::APPLICATION_RESUBMITTED->value,
            'entity_id' => $user->getKey(),
        ]);
    }

    public function test_non_rejected_account_cannot_resubmit(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute('resubmit.store', now()->addHour(), [
            'user' => $user->getKey(),
        ]);

        $this->post($url, [])->assertForbidden();
    }

    public function test_status_page_exposes_resubmit_link_only_when_rejected(): void
    {
        $rejected = User::factory()->rejected()->create();
        $pending = User::factory()->pending()->create();

        $this->get(URL::temporarySignedRoute('account.status', now()->addHour(), ['user' => $rejected->getKey()]))
            ->assertOk();
        $this->get(URL::temporarySignedRoute('account.status', now()->addHour(), ['user' => $pending->getKey()]))
            ->assertOk();
    }
}
