<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_verified_superadmin_and_audits(): void
    {
        $this->artisan('ajm:create-superadmin', [
            '--email' => 'root@example.com',
            '--name' => 'Root Admin',
            '--password' => 'a-very-long-password',
        ])->assertSuccessful();

        $user = User::where('email', 'root@example.com')->firstOrFail();

        $this->assertSame(UserRole::SUPERADMIN, $user->role);
        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::SUPERADMIN_PROVISIONED->value,
            'entity_id' => $user->getKey(),
        ]);
    }

    public function test_it_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'root@example.com']);

        $this->artisan('ajm:create-superadmin', [
            '--email' => 'root@example.com',
            '--name' => 'Root Admin',
            '--password' => 'a-very-long-password',
        ])->assertFailed();

        $this->assertSame(1, User::where('email', 'root@example.com')->count());
    }

    public function test_it_rejects_a_short_password(): void
    {
        $this->artisan('ajm:create-superadmin', [
            '--email' => 'root@example.com',
            '--name' => 'Root Admin',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
