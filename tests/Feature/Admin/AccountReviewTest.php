<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\B2gEvidenceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\B2gEvidenceSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AccountReviewTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->superadmin()->create();
    }

    private function confirmedAs(User $admin): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin);
    }

    public function test_non_superadmin_cannot_view_the_queue(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->get('/admin/akun')->assertForbidden();
    }

    public function test_superadmin_sees_the_pending_queue(): void
    {
        $admin = $this->superadmin();
        User::factory()->pending()->create();
        User::factory()->create();

        $this->actingAs($admin)
            ->get('/admin/akun')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Accounts/Index')
                ->has('applications', 1));
    }

    public function test_approval_requires_a_verified_email(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->pending()->create(['email_verified_at' => null]);

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$target->getKey()}/setujui", [
                'role' => UserRole::BUYER_B2B->value,
                'reason' => 'Dokumen lengkap.',
            ])
            ->assertSessionHasErrors();

        $this->assertSame(UserStatus::PENDING, $target->fresh()->status);
    }

    public function test_approval_grants_role_and_audits(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->pending()->create(['email_verified_at' => now()]);

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$target->getKey()}/setujui", [
                'role' => UserRole::BUYER_B2B->value,
                'reason' => 'Dokumen lengkap.',
            ])
            ->assertRedirect();

        $fresh = $target->fresh();
        $this->assertSame(UserStatus::ACTIVE, $fresh->status);
        $this->assertSame(UserRole::BUYER_B2B, $fresh->role);
        $this->assertNotNull($fresh->status_changed_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ACCOUNT_APPROVED->value,
            'entity_id' => $target->getKey(),
            'user_id' => $admin->getKey(),
        ]);
    }

    public function test_b2g_approval_requires_accepted_evidence(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->pending()->create(['email_verified_at' => now()]);

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$target->getKey()}/setujui", [
                'role' => UserRole::BUYER_B2G->value,
                'reason' => 'Coba B2G.',
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserStatus::PENDING, $target->fresh()->status);

        B2gEvidenceSubmission::create([
            'user_id' => $target->getKey(),
            'version' => 1,
            'path' => 'b2g/evidence/'.$target->getKey().'/bukti.pdf',
            'original_filename' => 'bukti.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'evidence_type' => 'kontrak',
            'status' => B2gEvidenceStatus::ACCEPTED,
        ]);

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$target->getKey()}/setujui", [
                'role' => UserRole::BUYER_B2G->value,
                'reason' => 'Bukti valid.',
            ])
            ->assertRedirect();

        $fresh = $target->fresh();
        $this->assertSame(UserStatus::ACTIVE, $fresh->status);
        $this->assertSame(UserRole::BUYER_B2G, $fresh->role);
        $this->assertNull($fresh->requested_role);
    }

    public function test_rejection_is_audited(): void
    {
        $admin = $this->superadmin();
        $target = User::factory()->pending()->create();

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$target->getKey()}/tolak", ['reason' => 'Tidak memenuhi syarat.'])
            ->assertRedirect();

        $this->assertSame(UserStatus::REJECTED, $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ACCOUNT_REJECTED->value,
            'entity_id' => $target->getKey(),
        ]);
    }
}
