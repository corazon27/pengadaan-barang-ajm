<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\RfqStatus;
use App\Http\Resources\Product\PublicProductResource;
use App\Models\Product;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use App\Notifications\RfqSubmittedNotification;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccountRfqCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_create_and_store(): void
    {
        $this->get('/akun/rfq/create')->assertRedirect(route('login'));

        $this->post('/akun/rfq', ['items' => []])->assertRedirect(route('login'));
    }

    public function test_create_route_resolves_before_the_detail_route(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->actingAs($user)
            ->get('/akun/rfq/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Account/Rfq/Create'));
    }

    public function test_buyer_b2b_can_open_the_create_page_with_a_safe_product_selector(): void
    {
        $user = User::factory()->buyerB2b()->create();

        Product::factory()->create([
            'sku' => 'SKU-ALPHA',
            'title' => 'Alpha',
            'base_price' => 1234.56,
            'margin_percentage' => 40.00,
            'tax_rate_percentage' => 11.00,
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->get('/akun/rfq/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Rfq/Create')
                ->has('products', 1)
                ->where('products.0.id', Product::first()->id)
                ->where('products.0.sku', 'SKU-ALPHA')
                ->where('products.0.title', 'Alpha')
                ->where('products.0.price', '1234.56')
                ->where('products.0.available', true)
                ->missing('products.0.base_price')
                ->missing('products.0.stock')
                ->missing('products.0.margin_percentage')
                ->missing('products.0.tax_rate_percentage'));
    }

    public function test_buyer_b2g_can_open_the_create_page(): void
    {
        $user = User::factory()->buyerB2g()->create();

        $this->actingAs($user)
            ->get('/akun/rfq/create')
            ->assertOk();
    }

    public function test_superadmin_cannot_open_the_buyer_create_page(): void
    {
        $admin = User::factory()->superadmin()->create();

        $this->actingAs($admin)
            ->get('/akun/rfq/create')
            ->assertForbidden();
    }

    public function test_buyer_can_submit_an_rfq_and_is_redirected_to_the_detail(): void
    {
        Notification::fake();

        $user = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($user)->post('/akun/rfq', [
            'notes' => 'Mohon penawaran harga',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 7,
                    'target_price' => 250000.00,
                    'notes' => 'Bisa segera?',
                ],
            ],
        ]);

        $rfq = Rfq::query()->firstOrFail();

        $response
            ->assertRedirect(route('account.rfqs.show', $rfq))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('rfqs', [
            'id' => $rfq->id,
            'user_id' => $user->id,
            'status' => RfqStatus::SUBMITTED->value,
            'notes' => 'Mohon penawaran harga',
        ]);
        $this->assertNotNull($rfq->rfq_number);

        $this->assertDatabaseHas('rfq_items', [
            'rfq_id' => $rfq->id,
            'product_id' => $product->id,
            'quantity' => 7,
            'target_price' => 250000.00,
            'notes' => 'Bisa segera?',
        ]);
    }

    public function test_authenticated_user_id_is_server_assigned_and_cannot_be_spoofed(): void
    {
        Notification::fake();

        $user = User::factory()->buyerB2b()->create();
        $victim = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post('/akun/rfq', [
            'user_id' => $victim->id,
            'status' => RfqStatus::APPROVED->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $rfq = Rfq::query()->firstOrFail();

        $this->assertSame($user->id, $rfq->user_id);
        $this->assertSame(RfqStatus::SUBMITTED, $rfq->status);
    }

    public function test_superadmin_cannot_submit_through_the_buyer_web_route(): void
    {
        $admin = User::factory()->superadmin()->create();
        $product = Product::factory()->create();

        $this->actingAs($admin)->post('/akun/rfq', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertForbidden();

        $this->assertDatabaseCount('rfqs', 0);
        $this->assertDatabaseCount('rfq_items', 0);
    }

    public function test_inactive_account_is_blocked_before_creation(): void
    {
        $user = User::factory()->pending()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->get('/akun/rfq/create')->assertRedirect(route('login'));

        $this->actingAs($user)->post('/akun/rfq', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('rfqs', 0);
    }

    public function test_items_are_required_and_validated(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->actingAs($user)
            ->from('/akun/rfq/create')
            ->post('/akun/rfq', ['items' => []])
            ->assertRedirect('/akun/rfq/create')
            ->assertSessionHasErrors(['items']);

        $this->actingAs($user)
            ->from('/akun/rfq/create')
            ->post('/akun/rfq', [
                'items' => [
                    ['product_id' => 'not-a-uuid', 'quantity' => 0],
                ],
            ])
            ->assertRedirect('/akun/rfq/create')
            ->assertSessionHasErrors(['items.0.product_id', 'items.0.quantity']);

        $this->assertDatabaseCount('rfqs', 0);
    }

    public function test_rfq_can_be_submitted_for_an_out_of_stock_product(): void
    {
        Notification::fake();

        $user = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create(['stock' => 0]);

        $this->actingAs($user)->post('/akun/rfq', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('rfqs', ['user_id' => $user->id]);
        $this->assertDatabaseHas('rfq_items', ['product_id' => $product->id, 'quantity' => 3]);
    }

    public function test_selected_products_mark_availability_from_stock_only(): void
    {
        $user = User::factory()->buyerB2b()->create();

        Product::factory()->create(['title' => 'Alpha', 'stock' => 0]);
        Product::factory()->create(['title' => 'Beta', 'stock' => 4]);

        $this->actingAs($user)
            ->get('/akun/rfq/create')
            ->assertInertia(fn (Assert $page) => $page
                ->has('products', 2)
                ->where('products.0.title', 'Alpha')
                ->where('products.0.available', false)
                ->where('products.1.title', 'Beta')
                ->where('products.1.available', true));
    }

    public function test_all_superadmins_are_notified_after_a_successful_submission(): void
    {
        Notification::fake();

        $user = User::factory()->buyerB2b()->create();
        $adminOne = User::factory()->superadmin()->create();
        $adminTwo = User::factory()->superadmin()->create();
        $otherBuyer = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();

        $this->actingAs($user)->post('/akun/rfq', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->assertRedirect();

        Notification::assertSentTo($adminOne, RfqSubmittedNotification::class);
        Notification::assertSentTo($adminTwo, RfqSubmittedNotification::class);
        Notification::assertNotSentTo($otherBuyer, RfqSubmittedNotification::class);
    }

    public function test_product_selector_exposes_only_the_public_projection(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $product = Product::factory()->create([
            'sku' => 'SKU-GUARD',
            'title' => 'Guard',
            'base_price' => 1234.56,
            'margin_percentage' => 40.00,
            'tax_rate_percentage' => 11.00,
            'estimated_shipping' => 99.00,
            'tkdn_percentage' => 25.00,
            'is_sni' => true,
            'stock' => 5,
        ]);

        $this->actingAs($user)
            ->get('/akun/rfq/create')
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($product): void {
                $page->has('products', 1);

                $payload = $page->toArray()['props']['products'][0];

                $this->assertEqualsCanonicalizing(
                    ['id', 'sku', 'title', 'price', 'available'],
                    array_keys($payload),
                );

                foreach ([
                    'base_price',
                    'margin_percentage',
                    'tax_rate_percentage',
                    'estimated_shipping',
                    'datasheet_url',
                    'stock',
                    'tkdn_percentage',
                    'is_sni',
                    'slug',
                    'description',
                ] as $field) {
                    $this->assertArrayNotHasKey($field, $payload);
                }

                // The buyer-facing `price` must be the verified public value:
                // identical to the public API projection and to the figure the
                // real order flow charges (OrderItem unit_price is base_price).
                $this->assertSame((new PublicProductResource($product))->resolve()['price'], $payload['price']);
                $this->assertSame((string) $product->base_price, $payload['price']);
            });
    }

    public function test_audit_failure_rolls_back_the_rfq_and_suppresses_notifications(): void
    {
        Notification::fake();

        $user = User::factory()->buyerB2b()->create();
        $product = Product::factory()->create();

        $this->mock(AuditLogger::class, function ($mock): void {
            $mock->shouldReceive('strictLog')->once()->andThrow(new \RuntimeException('audit store unavailable'));
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($user)->post('/akun/rfq', [
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ]);
            $this->fail('Expected the audit failure to abort the submission.');
        } catch (\RuntimeException $e) {
            $this->assertSame('audit store unavailable', $e->getMessage());
        }

        $this->assertDatabaseCount('rfqs', 0);
        $this->assertSame(0, RfqItem::query()->count());
        Notification::assertNothingSent();
    }
}
