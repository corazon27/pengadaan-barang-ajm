<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AccountLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SuspendReactivateTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedAs(User $admin): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin);
    }

    public function test_suspend_revokes_credentials_and_audits(): void
    {
        $admin = User::factory()->superadmin()->create();
        $target = User::factory()->create();
        $target->createToken('existing-token');

        DB::table('sessions')->insert([
            'id' => 'test-session',
            'user_id' => $target->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => 'payload',
            'last_activity' => time(),
        ]);

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$target->getKey()}/tangguhkan", ['reason' => 'Indikasi penyalahgunaan.'])
            ->assertRedirect();

        $fresh = $target->fresh();
        $this->assertSame(UserStatus::SUSPENDED, $fresh->status);
        $this->assertNull($fresh->remember_token);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->getKey()]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ACCOUNT_SUSPENDED->value,
            'entity_id' => $target->getKey(),
        ]);
    }

    public function test_superadmin_cannot_suspend_themselves(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$admin->getKey()}/tangguhkan", ['reason' => 'Uji.'])
            ->assertForbidden();

        $this->assertSame(UserStatus::ACTIVE, $admin->fresh()->status);
    }

    public function test_reactivation_restores_active_status(): void
    {
        $admin = User::factory()->superadmin()->create();
        $target = User::factory()->suspended()->create();

        $this->confirmedAs($admin)
            ->post("/admin/akun/{$target->getKey()}/aktifkan", ['reason' => 'Selesai ditinjau.'])
            ->assertRedirect();

        $this->assertSame(UserStatus::ACTIVE, $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ACCOUNT_REACTIVATED->value,
            'entity_id' => $target->getKey(),
        ]);
    }

    public function test_last_active_superadmin_cannot_be_suspended(): void
    {
        $actor = User::factory()->superadmin()->create();
        $target = User::factory()->superadmin()->create();

        // Simulate the actor no longer being an active superadmin (e.g. a
        // concurrent change) so suspending $target would leave zero active.
        $actor->forceFill(['status' => UserStatus::SUSPENDED])->save();

        $this->expectException(ValidationException::class);

        app(AccountLifecycleService::class)->suspend($actor->fresh(), $target, 'Uji invarian.');
    }
}
