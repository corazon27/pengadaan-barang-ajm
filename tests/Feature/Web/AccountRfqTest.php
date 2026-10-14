<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\RfqStatus;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountRfqTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/akun/rfq')->assertRedirect(route('login'));
    }

    public function test_index_lists_only_own_rfqs(): void
    {
        $user = User::factory()->buyerB2g()->create();
        $other = User::factory()->buyerB2g()->create();

        Rfq::factory()->count(3)->create(['user_id' => $user->id]);
        Rfq::factory()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get('/akun/rfq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Rfq/Index')
                ->has('rfqs', 3)
                ->where('pagination.total', 3)
                ->where('pagination.baseUrl', '/akun/rfq'));
    }

    public function test_owner_can_view_their_rfq_detail_with_items(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create([
            'user_id' => $user->id,
            'rfq_number' => 'RFQ-TEST-1',
            'status' => RfqStatus::QUOTED,
        ]);
        $product = Product::factory()->create(['sku' => 'SKU-RFQ', 'title' => 'Meja']);

        RfqItem::factory()->create([
            'rfq_id' => $rfq->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $this->actingAs($user)
            ->get("/akun/rfq/{$rfq->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Rfq/Show')
                ->where('rfq.rfq_number', 'RFQ-TEST-1')
                ->where('rfq.status_label', 'Penawaran Tersedia')
                ->has('rfq.items', 1)
                ->where('rfq.items.0.product_sku', 'SKU-RFQ'));
    }

    public function test_cross_buyer_rfq_detail_is_forbidden(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $other = User::factory()->buyerB2b()->create();

        $rfq = Rfq::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user)
            ->get("/akun/rfq/{$rfq->id}")
            ->assertForbidden();
    }
}
