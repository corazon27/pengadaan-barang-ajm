<?php

declare(strict_types=1);

namespace Tests\Feature\Web\Admin;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPaymentQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_payments_and_can_filter_by_status(): void
    {
        $admin = User::factory()->superadmin()->create();

        Payment::factory()->count(2)->create(['status' => PaymentStatus::PENDING_VERIFICATION]);
        Payment::factory()->count(3)->create(['status' => PaymentStatus::VERIFIED]);

        $this->actingAs($admin)
            ->get('/admin/pembayaran')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Payments/Index')
                ->where('pagination.total', 5)
                ->where('pagination.baseUrl', '/admin/pembayaran'));

        $this->actingAs($admin)
            ->get('/admin/pembayaran?status=PENDING_VERIFICATION')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('pagination.total', 2));
    }

    public function test_payment_detail_exposes_only_a_has_proof_flag(): void
    {
        $admin = User::factory()->superadmin()->create();
        $payment = Payment::factory()->create([
            'proof_file_url' => 'https://private.example.com/proof.jpg',
        ]);

        $this->actingAs($admin)
            ->get("/admin/pembayaran/{$payment->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Payments/Show')
                ->where('payment.has_proof', true)
                ->missing('payment.proof_file_url'));
    }

    public function test_buyer_is_forbidden_from_admin_payment_surfaces(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $payment = Payment::factory()->create();

        $this->actingAs($buyer)->get('/admin/pembayaran')->assertForbidden();
        $this->actingAs($buyer)->get("/admin/pembayaran/{$payment->id}")->assertForbidden();
    }
}
