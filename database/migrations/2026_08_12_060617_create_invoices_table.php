<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignUuid('bast_id');
            $table->string('invoice_number', 50)->unique();
            $table->string('faktur_pajak_number', 50)->nullable();
            $table->string('invoice_pdf_url', 500);
            $table->string('faktur_pajak_url', 500)->nullable();
            $table->decimal('amount_due', 15, 2);
            $table->date('issued_date');
            $table->date('due_date');
            $table->enum('status', array_column(InvoiceStatus::cases(), 'value'))->default(InvoiceStatus::UNPAID->value);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(['bast_id', 'order_id'], 'invoices_bast_order_foreign')
                ->references(['id', 'order_id'])
                ->on('bast_documents')
                ->restrictOnDelete();

            $table->index('order_id');
            $table->index('due_date');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
