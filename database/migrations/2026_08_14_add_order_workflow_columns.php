<?php

use App\Enums\BastStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evolve the order workflow schema: new order statuses, signable BAST
     * documents, and invoice amount breakdowns.
     */
    public function up(): void
    {
        // Bring existing MySQL databases up to date with the new OrderStatus
        // values. SQLite tests run on a fresh in-memory database so the updated
        // enum is already in place there.
        if (DB::getDriverName() === 'mysql') {
            // Map any legacy statuses (e.g. DRAFT, WAITING_PO) into the new
            // lifecycle before narrowing the enum, or the ALTER would fail
            // under strict mode.
            DB::table('orders')
                ->whereNotIn('status', array_column(OrderStatus::cases(), 'value'))
                ->update(['status' => OrderStatus::PENDING_PAYMENT->value]);

            $values = array_map(
                static fn (OrderStatus $status) => "'{$status->value}'",
                OrderStatus::cases()
            );

            DB::statement(
                'ALTER TABLE orders MODIFY status ENUM('.implode(',', $values).') NOT NULL DEFAULT \''.OrderStatus::PENDING_PAYMENT->value.'\''
            );
        }

        Schema::table('bast_documents', function (Blueprint $table) {
            $table->enum('status', array_column(BastStatus::cases(), 'value'))->default(BastStatus::PENDING_SIGNATURE->value)->after('bast_number');
            $table->foreignUuid('signed_by')->nullable()->constrained('users')->nullOnDelete()->after('signed_date');
            $table->timestamp('signed_at')->nullable()->after('signed_by');
            $table->text('notes')->nullable()->after('signed_at');

            $table->string('bast_document_url', 500)->nullable()->change();
            $table->date('signed_date')->nullable()->change();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 15, 2)->nullable()->after('amount_due');
            $table->decimal('tax_amount', 15, 2)->nullable()->after('subtotal');
            $table->decimal('grand_total', 15, 2)->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            $oldValues = ['DRAFT', 'WAITING_PO', 'PROCESSING', 'SHIPPED', 'BAST_SIGNED', 'INVOICED', 'PAID', 'CANCELLED'];
            DB::statement(
                'ALTER TABLE orders MODIFY status ENUM('.implode(',', array_map(static fn ($v) => "'{$v}'", $oldValues)).') NOT NULL DEFAULT \'DRAFT\''
            );
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'tax_amount', 'grand_total']);
        });

        Schema::table('bast_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_by');
            $table->dropColumn(['status', 'signed_at', 'notes']);

            $table->string('bast_document_url', 500)->nullable(false)->change();
            $table->date('signed_date')->nullable(false)->change();
        });
    }
};
