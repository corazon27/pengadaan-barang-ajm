<?php

use App\Enums\RfqStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rfq_number', 50)->unique();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', array_column(RfqStatus::cases(), 'value'))->default(RfqStatus::SUBMITTED->value);
            $table->string('quotation_pdf_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });

        Schema::create('rfq_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->integer('quantity')->unsigned();
            $table->decimal('negotiated_price', 15, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
