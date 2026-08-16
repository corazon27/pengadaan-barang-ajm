<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2E: introduce the REVIEW_REQUIRED invoice computation state.
     *
     * When the authoritative tax engine cannot resolve/compute PPN at BAST
     * signing, the invoice is flagged REVIEW_REQUIRED (intermediate state,
     * zero tax, no snapshots) and only the recalculate-tax flow moves it back
     * to UNPAID. Also tracks which tax calculation algorithm version produced
     * the amounts on the invoice.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('status', array_column(InvoiceStatus::cases(), 'value'))
                ->default(InvoiceStatus::UNPAID->value)
                ->change();

            $table->string('tax_calculation_version', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('tax_calculation_version');

            $table->enum('status', [
                InvoiceStatus::UNPAID->value,
                InvoiceStatus::PARTIALLY_PAID->value,
                InvoiceStatus::OVERDUE->value,
                InvoiceStatus::PAID->value,
            ])->default(InvoiceStatus::UNPAID->value)->change();
        });
    }
};
