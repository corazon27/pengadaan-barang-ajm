<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTerm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evolve the schema for Module 5: payment terms, tax breakdown on
     * invoices, per-product PPh withholding, and the payments ledger.
     */
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'tax_amount')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->renameColumn('tax_amount', 'ppn_amount');
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('pph_amount', 15, 2)->nullable()->after('ppn_amount');
            $table->enum('payment_term', array_column(PaymentTerm::cases(), 'value'))->default(PaymentTerm::TOP_30->value)->after('pph_amount');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('pph_rate_percentage', 5, 2)->nullable()->after('tax_rate_percentage');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('payment_method', array_column(PaymentMethod::cases(), 'value'))->default(PaymentMethod::BANK_TRANSFER->value);
            $table->date('payment_date');
            $table->string('proof_file_url', 500);
            $table->text('notes')->nullable();
            $table->enum('status', array_column(PaymentStatus::cases(), 'value'))->default(PaymentStatus::PENDING_VERIFICATION->value);
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('pph_rate_percentage');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['pph_amount', 'payment_term']);
        });

        if (Schema::hasColumn('invoices', 'ppn_amount')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->renameColumn('ppn_amount', 'tax_amount');
            });
        }
    }
};
