<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Admin;

use App\Enums\RfqStatus;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminRfqQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_rfqs_from_all_buyers(): void
    {
        $admin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        Rfq::factory()->count(2)->create(['user_id' => $buyer->id]);
        Rfq::factory()->count(3)->create(['user_id' => $other->id]);

        $this->actingAs($admin)
            ->get('/admin/rfq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rfq/Index')
                ->has('rfqs', 5)
                ->where('pagination.total', 5)
                ->where('pagination.baseUrl', '/admin/rfq'));
    }

    public function test_status_filter_narrows_the_queue(): void
    {
        $admin = User::factory()->superadmin()->create();

        Rfq::factory()->count(2)->create(['status' => RfqStatus::APPROVED]);
        Rfq::factory()->count(3)->create(['status' => RfqStatus::SUBMITTED]);

        $this->actingAs($admin)
            ->get('/admin/rfq?status=APPROVED')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 2));
    }

    public function test_unknown_status_filter_is_ignored(): void
    {
        $admin = User::factory()->superadmin()->create();
        Rfq::factory()->count(2)->create();

        $this->actingAs($admin)
            ->get('/admin/rfq?status=NOT_A_STATUS')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 2));
    }

    public function test_admin_can_view_any_rfq_detail(): void
    {
        $admin = User::factory()->superadmin()->create();
        $rfq = Rfq::factory()->create(['rfq_number' => 'RFQ-ADMIN-1']);

        $this->actingAs($admin)
            ->get("/admin/rfq/{$rfq->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Rfq/Show')
                ->where('rfq.rfq_number', 'RFQ-ADMIN-1'));
    }

    public function test_buyer_is_forbidden_from_admin_rfq_surfaces(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create();

        $this->actingAs($buyer)->get('/admin/rfq')->assertForbidden();
        $this->actingAs($buyer)->get("/admin/rfq/{$rfq->id}")->assertForbidden();
    }
}
