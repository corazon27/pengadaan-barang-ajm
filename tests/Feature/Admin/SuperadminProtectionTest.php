<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedAs(User $admin): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin);
    }

    public function test_a_buyer_cannot_reach_admin_endpoints(): void
    {
        $buyer = User::factory()->create();
        $target = User::factory()->pending()->create(['email_verified_at' => now()]);

        $this->actingAs($buyer)->get('/admin/akun')->assertForbidden();
        $this->actingAs($buyer)->get("/admin/akun/{$target->getKey()}")->assertForbidden();

        $this->confirmedAs($buyer)
            ->post("/admin/akun/{$target->getKey()}/setujui", [
                'role' => UserRole::BUYER_B2B->value,
                'reason' => 'Tidak berwenang.',
            ])->assertForbidden();
    }

    public function test_admin_mutation_requires_recent_password_confirmation(): void
    {
        $admin = User::factory()->superadmin()->create();
        $target = User::factory()->pending()->create(['email_verified_at' => now()]);

        // No password-confirmation session present.
        $this->actingAs($admin)
            ->post("/admin/akun/{$target->getKey()}/setujui", [
                'role' => UserRole::BUYER_B2B->value,
                'reason' => 'Dokumen lengkap.',
            ])->assertRedirect(route('password.confirm'));

        $this->assertSame(UserStatus::PENDING, $target->fresh()->status);
    }

    public function test_a_superadmin_cannot_approve_themselves(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$admin->getKey()}/setujui", [
                'role' => UserRole::BUYER_B2B->value,
                'reason' => 'Naik pangkat.',
            ])->assertForbidden();
    }
}
