<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_audited(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::USER_LOGIN->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }

    public function test_failed_login_is_audited(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::LOGIN_FAILED->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }

    public function test_failed_login_for_unknown_email_is_still_audited(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'ghost@corporation.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'action' => AuditAction::LOGIN_FAILED->value,
        ]);
    }

    public function test_logout_is_audited(): void
    {
        $user = User::factory()->buyerB2b()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::USER_LOGOUT->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }

    public function test_throttled_login_is_audited(): void
    {
        $user = User::factory()->buyerB2b()->create();

        $payload = [
            'email' => $user->email,
            'password' => 'wrong-password',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', $payload)->assertStatus(429);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => AuditAction::LOGIN_THROTTLED->value,
            'entity_type' => 'User',
            'entity_id' => $user->id,
        ]);
    }

    public function test_profile_update_is_audited(): void
    {
        $user = User::factory()->buyerB2b()->create(['full_name' => 'Budi Santoso']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'full_name' => 'Budi Santoso Baru',
            'company_name' => $user->company_name,
            'npwp_number' => $user->npwp_number,
            'address' => $user->address,
            'phone_number' => $user->phone_number,
        ])->assertOk();

        $log = AuditLog::where('user_id', $user->id)
            ->where('action', AuditAction::PROFILE_UPDATED->value)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('User', $log->entity_type);
        $this->assertSame('Budi Santoso', $log->previous_state['full_name']);
        $this->assertSame('Budi Santoso Baru', $log->new_state['full_name']);
    }

    public function test_product_crud_is_audited(): void
    {
        $admin = User::factory()->superadmin()->create();
        Sanctum::actingAs($admin);

        $payload = [
            'sku' => 'P-1000',
            'title' => 'Alat Tulis Kantor',
            'slug' => 'alat-tulis-kantor',
            'description' => 'Perlengkapan ATK',
            'base_price' => '100000',
            'margin_percentage' => '10',
            'tax_rate_percentage' => '11',
            'pph_rate_percentage' => '2',
            'estimated_shipping' => '5000',
            'tkdn_percentage' => '40',
            'is_sni' => true,
            'warranty_info' => 'Garansi 1 tahun',
            'datasheet_url' => 'https://example.com/datasheet',
            'stock' => 10,
        ];

        $this->postJson('/api/v1/products', $payload)->assertCreated();

        $product = Product::where('sku', 'P-1000')->first();
        $this->assertNotNull($product);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => AuditAction::PRODUCT_CREATED->value,
            'entity_type' => 'Product',
            'entity_id' => $product->id,
        ]);

        $this->putJson("/api/v1/products/{$product->id}", [
            'sku' => 'P-1000',
            'title' => 'Alat Tulis Kantor Premium',
            'slug' => 'alat-tulis-kantor',
            'base_price' => '150000',
            'stock' => 10,
        ])->assertOk();

        $updateLog = AuditLog::where('entity_id', $product->id)
            ->where('action', AuditAction::PRODUCT_UPDATED->value)
            ->first();
        $this->assertNotNull($updateLog);
        $this->assertSame('Alat Tulis Kantor', $updateLog->previous_state['title']);
        $this->assertSame('Alat Tulis Kantor Premium', $updateLog->new_state['title']);

        $this->deleteJson("/api/v1/products/{$product->id}")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => AuditAction::PRODUCT_DELETED->value,
            'entity_type' => 'Product',
            'entity_id' => $product->id,
        ]);
    }

    public function test_invoice_audit_snapshot_captures_tax_state_fields(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::REVIEW_REQUIRED,
            'amount_due' => '0.00',
            'subtotal' => '200000.00',
            'ppn_amount' => '0.00',
            'grand_total' => '0.00',
            'tax_calculation_version' => null,
        ]);

        $snapshot = app(AuditLogger::class)->snapshot($invoice);

        // Module-8 core fields are preserved.
        $this->assertSame(InvoiceStatus::REVIEW_REQUIRED->value, $snapshot['status']);
        $this->assertSame('0.00', $snapshot['amount_due']);
        $this->assertSame('0.00', $snapshot['grand_total']);
        $this->assertNull($snapshot['paid_at']);

        // W1 hardening: tax computation fields are captured too.
        $this->assertSame('200000.00', $snapshot['subtotal']);
        $this->assertSame('0.00', $snapshot['ppn_amount']);
        $this->assertNull($snapshot['tax_calculation_version']);
    }
}
