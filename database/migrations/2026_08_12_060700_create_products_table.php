<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('base_price', 15, 2);
            $table->decimal('margin_percentage', 5, 2)->nullable();
            $table->decimal('tax_rate_percentage', 5, 2)->nullable();
            $table->decimal('estimated_shipping', 15, 2)->nullable();
            $table->decimal('tkdn_percentage', 5, 2)->nullable();
            $table->boolean('is_sni')->nullable();
            $table->string('warranty_info')->nullable();
            $table->string('datasheet_url')->nullable();
            $table->integer('stock')->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
