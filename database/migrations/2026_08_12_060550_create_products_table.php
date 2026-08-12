<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('sku', 50)->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->decimal('base_price', 15, 2);
            $table->decimal('margin_percentage', 5, 2)->unsigned()->default(0.00);
            $table->decimal('tax_rate_percentage', 5, 2)->unsigned()->default(11.00);
            $table->decimal('estimated_shipping', 15, 2)->default(0.00);
            $table->decimal('tkdn_percentage', 5, 2)->unsigned()->nullable();
            $table->boolean('is_sni')->default(false);
            $table->string('warranty_info', 100)->nullable();
            $table->string('datasheet_url', 500)->nullable();
            $table->integer('stock')->unsigned()->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
