<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\RfqStatus;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfqTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_can_submit_rfq_with_items(): void
    {
        $user = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('/api/v1/rfqs', [
            'notes' => 'Please quote for these items',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 10,
                    'target_price' => 100000.00,
                    'notes' => 'Urgent delivery',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'rfq_number',
                    'status',
                    'items' => [
                        '*' => [
                            'product_id',
                            'quantity',
                            'target_price',
                        ],
                    ],
                ],
                'errors',
            ])
            ->assertJsonPath('data.status', RfqStatus::SUBMITTED->value)
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 10)
            ->assertJsonPath('data.items.0.target_price', '100000.00');

        $this->assertDatabaseHas('rfqs', [
            'user_id' => $user->id,
            'status' => RfqStatus::SUBMITTED->value,
        ]);

        $this->assertDatabaseHas('rfq_items', [
            'rfq_id' => $response->json('data.id'),
            'product_id' => $product->id,
            'quantity' => 10,
            'target_price' => 100000.00,
        ]);
    }

    public function test_buyer_can_only_view_own_rfqs(): void
    {
        $buyer1 = User::factory()->buyerB2b()->create();
        $buyer2 = User::factory()->buyerB2b()->create();

        $rfq1 = Rfq::factory()->create(['user_id' => $buyer1->id]);
        $rfq2 = Rfq::factory()->create(['user_id' => $buyer2->id]);

        $this->actingAs($buyer1);

        $response = $this->getJson('/api/v1/rfqs');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $rfq1->id)
            ->assertJsonCount(1, 'data');
    }

    public function test_superadmin_can_view_all_rfqs(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer1 = User::factory()->buyerB2b()->create();
        $buyer2 = User::factory()->buyerB2b()->create();

        Rfq::factory()->create(['user_id' => $buyer1->id]);
        Rfq::factory()->create(['user_id' => $buyer2->id]);

        $this->actingAs($superadmin);

        $response = $this->getJson('/api/v1/rfqs');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_superadmin_can_respond_to_rfq(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $buyer = User::factory()->buyerB2b()->create();

        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::SUBMITTED]);
        $product = Product::factory()->create();
        $rfqItem = RfqItem::factory()->create(['rfq_id' => $rfq->id, 'product_id' => $product->id, 'quantity' => 5]);

        $this->actingAs($superadmin);

        $response = $this->postJson("/api/v1/rfqs/{$rfq->id}/respond", [
            'admin_notes' => 'Quoted with 10% discount',
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [
                [
                    'rfq_item_id' => $rfqItem->id,
                    'offered_price' => 95000.00,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RfqStatus::QUOTED->value)
            ->assertJsonPath('data.items.0.offered_price', '95000.00')
            ->assertJsonStructure([
                'data' => [
                    'valid_until',
                ],
            ]);

        $rfq->refresh();
        $this->assertEquals(RfqStatus::QUOTED, $rfq->status);
        $this->assertNotNull($rfq->valid_until);
        $rfqItem->refresh();
        $this->assertEquals(95000.00, $rfqItem->negotiated_price);
    }

    public function test_buyer_can_accept_quoted_rfq(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::QUOTED]);

        $this->actingAs($buyer);

        $response = $this->patchJson("/api/v1/rfqs/{$rfq->id}/status", [
            'status' => RfqStatus::APPROVED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RfqStatus::APPROVED->value);

        $rfq->refresh();
        $this->assertEquals(RfqStatus::APPROVED, $rfq->status);
    }

    public function test_buyer_can_reject_quoted_rfq(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::QUOTED]);

        $this->actingAs($buyer);

        $response = $this->patchJson("/api/v1/rfqs/{$rfq->id}/status", [
            'status' => RfqStatus::REJECTED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RfqStatus::REJECTED->value);

        $rfq->refresh();
        $this->assertEquals(RfqStatus::REJECTED, $rfq->status);
    }

    public function test_buyer_can_cancel_submitted_rfq(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::SUBMITTED]);

        $this->actingAs($buyer);

        $response = $this->patchJson("/api/v1/rfqs/{$rfq->id}/status", [
            'status' => RfqStatus::CANCELLED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RfqStatus::CANCELLED->value);

        $rfq->refresh();
        $this->assertEquals(RfqStatus::CANCELLED, $rfq->status);
    }

    public function test_buyer_can_cancel_quoted_rfq(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::QUOTED]);

        $this->actingAs($buyer);

        $response = $this->patchJson("/api/v1/rfqs/{$rfq->id}/status", [
            'status' => RfqStatus::CANCELLED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', RfqStatus::CANCELLED->value);

        $rfq->refresh();
        $this->assertEquals(RfqStatus::CANCELLED, $rfq->status);
    }

    public function test_non_owner_receives_403_on_respond(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id]);
        $rfqItem = RfqItem::factory()->create(['rfq_id' => $rfq->id]);

        $otherBuyer = User::factory()->buyerB2b()->create();
        $this->actingAs($otherBuyer);

        $response = $this->postJson("/api/v1/rfqs/{$rfq->id}/respond", [
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [
                [
                    'rfq_item_id' => $rfqItem->id,
                    'offered_price' => 50000.00,
                ],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_non_owner_receives_403_on_status_update(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::QUOTED]);

        $otherBuyer = User::factory()->buyerB2b()->create();
        $this->actingAs($otherBuyer);

        $response = $this->patchJson("/api/v1/rfqs/{$rfq->id}/status", [
            'status' => RfqStatus::APPROVED->value,
        ]);

        $response->assertForbidden();
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id, 'status' => RfqStatus::APPROVED]);

        $this->actingAs($buyer);

        $response = $this->patchJson("/api/v1/rfqs/{$rfq->id}/status", [
            'status' => RfqStatus::CANCELLED->value,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.status.0', 'Tidak dapat mengubah status dari APPROVED ke CANCELLED.');
    }

    public function test_rfq_show_returns_items_with_product_details(): void
    {
        $buyer = User::factory()->buyerB2b()->create();
        $rfq = Rfq::factory()->create(['user_id' => $buyer->id]);
        $product = Product::factory()->create(['title' => 'Test Product', 'sku' => 'SKU-001']);
        RfqItem::factory()->create(['rfq_id' => $rfq->id, 'product_id' => $product->id, 'quantity' => 3]);

        $this->actingAs($buyer);

        $response = $this->getJson("/api/v1/rfqs/{$rfq->id}");

        $response->assertOk()
            ->assertJsonPath('data.items.0.product.title', 'Test Product')
            ->assertJsonPath('data.items.0.product.sku', 'SKU-001')
            ->assertJsonPath('data.items.0.quantity', 3);
    }
}
