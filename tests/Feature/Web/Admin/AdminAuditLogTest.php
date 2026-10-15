<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_audit_entries(): void
    {
        $admin = User::factory()->superadmin()->create();
        AuditLog::factory()->count(2)->create([
            'entity_type' => 'Rfq',
            'action' => 'RFQ_CREATED',
        ]);

        $this->actingAs($admin)
            ->get('/admin/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AuditLogs/Index')
                ->where('pagination.total', 2)
                ->where('pagination.baseUrl', '/admin/audit'));
    }

    public function test_entity_type_and_action_filters_narrow_the_list(): void
    {
        $admin = User::factory()->superadmin()->create();

        AuditLog::factory()->create(['entity_type' => 'Rfq', 'action' => 'RFQ_CREATED']);
        AuditLog::factory()->count(2)->create(['entity_type' => 'Order', 'action' => 'ORDER_CREATED']);

        $this->actingAs($admin)
            ->get('/admin/audit?entity_type=Rfq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 1));

        $this->actingAs($admin)
            ->get('/admin/audit?action=ORDER_CREATED')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 2));
    }

    public function test_unknown_filters_are_ignored(): void
    {
        $admin = User::factory()->superadmin()->create();
        AuditLog::factory()->count(3)->create(['entity_type' => 'Rfq', 'action' => 'RFQ_CREATED']);

        $this->actingAs($admin)
            ->get('/admin/audit?entity_type=Evil&action=NOT_REAL')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 3));
    }

    public function test_state_snapshots_are_curated_and_pii_is_redacted(): void
    {
        $admin = User::factory()->superadmin()->create();

        AuditLog::factory()->create([
            'entity_type' => 'User',
            'action' => 'PROFILE_UPDATED',
            'previous_state' => [
                'status' => 'PENDING',
                'email' => 'secret@example.com',
                'full_name' => 'Budi Rahasia',
                'company_name' => 'PT Rahasia',
            ],
            'new_state' => [
                'status' => 'ACTIVE',
                'email' => 'secret@example.com',
                'full_name' => 'Budi Rahasia',
                'company_name' => 'PT Rahasia',
            ],
        ]);

        $this->actingAs($admin)
            ->get('/admin/audit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.0.previous_state.fields.status', 'PENDING')
                ->where('logs.0.new_state.fields.status', 'ACTIVE')
                ->where('logs.0.previous_state.redacted', 3)
                ->missing('logs.0.previous_state.fields.email')
                ->missing('logs.0.previous_state.fields.full_name')
                ->missing('logs.0.previous_state.fields.company_name')
                ->missing('logs.0.new_state.fields.email'));
    }

    public function test_buyer_is_forbidden_from_audit_trail(): void
    {
        $buyer = User::factory()->buyerB2b()->create();

        $this->actingAs($buyer)->get('/admin/audit')->assertForbidden();
    }
}
