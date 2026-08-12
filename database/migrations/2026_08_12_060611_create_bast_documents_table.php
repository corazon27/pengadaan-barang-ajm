<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bast_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('orders')->restrictOnDelete();
            $table->string('bast_number', 50)->unique();
            $table->string('bast_document_url', 500);
            $table->date('signed_date');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bast_documents');
    }
};
