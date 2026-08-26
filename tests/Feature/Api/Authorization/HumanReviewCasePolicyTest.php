<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Authorization;

use App\Enums\HumanReviewStatus;
use App\Enums\HumanReviewType;
use App\Models\HumanReviewCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HumanReviewCasePolicyTest extends TestCase
{
    use RefreshDatabase;

    private function createCase(): HumanReviewCase
    {
        return HumanReviewCase::create([
            'type' => HumanReviewType::PDP,
            'active_review_key' => 'pdp_review_1',
            'rule_id' => '00000000-0000-0000-0000-000000000001',
            'trigger' => 'CONSENT_WITHDRAWN',
            'subject_type' => 'App\\Models\\ConsentRecord',
            'subject_id' => '00000000-0000-0000-0000-000000000001',
            'status' => HumanReviewStatus::PENDING,
        ]);
    }

    public function test_superadmin_can_view_any_human_review_cases(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('viewAny', HumanReviewCase::class));
    }

    public function test_superadmin_can_view_human_review_case(): void
    {
        $admin = User::factory()->superadmin()->create();
        $case = $this->createCase();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('view', $case));
    }

    public function test_superadmin_can_create_human_review_case(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('create', HumanReviewCase::class));
    }

    public function test_superadmin_can_update_human_review_case(): void
    {
        $admin = User::factory()->superadmin()->create();
        $case = $this->createCase();

        $this->actingAs($admin);

        $this->assertTrue($admin->can('update', $case));
    }

    public function test_regular_user_cannot_view_any_human_review_cases(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('viewAny', HumanReviewCase::class));
    }

    public function test_regular_user_cannot_view_human_review_case(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $case = $this->createCase();

        $this->actingAs($user);

        $this->assertFalse($user->can('view', $case));
    }

    public function test_regular_user_cannot_create_human_review_case(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->actingAs($user);

        $this->assertFalse($user->can('create', HumanReviewCase::class));
    }

    public function test_regular_user_cannot_update_human_review_case(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $case = $this->createCase();

        $this->actingAs($user);

        $this->assertFalse($user->can('update', $case));
    }

    public function test_nobody_can_delete_human_review_case(): void
    {
        $admin = User::factory()->superadmin()->create();
        $case = $this->createCase();

        $this->actingAs($admin);

        $this->assertFalse($admin->can('delete', $case));
    }
}
