<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccountLifecycleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_the_lifecycle_columns(): void
    {
        foreach (['status', 'status_reason', 'status_changed_at', 'requested_role'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('users', $column),
                "Expected users table to have column [{$column}].",
            );
        }
    }

    public function test_b2g_evidence_table_has_expected_columns(): void
    {
        foreach (['user_id', 'version', 'path', 'status', 'reviewed_by', 'review_reason'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('b2g_evidence_submissions', $column),
                "Expected b2g_evidence_submissions table to have column [{$column}].",
            );
        }
    }

    public function test_a_directly_constructed_user_defaults_to_pending(): void
    {
        $user = new User([
            'email' => 'default@example.com',
            'full_name' => 'Default User',
            'company_name' => 'PT Default',
            'address' => 'Jl. Default No. 1',
            'phone_number' => '081200000000',
            'password' => 'password123',
        ]);
        $user->save();

        $this->assertSame(UserStatus::PENDING, $user->fresh()->status);
    }
}
