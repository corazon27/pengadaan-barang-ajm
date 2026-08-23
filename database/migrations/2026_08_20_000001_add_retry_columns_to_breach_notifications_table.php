<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('breach_notifications', function (Blueprint $table) {
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('last_retry_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('breach_notifications', function (Blueprint $table) {
            $table->dropColumn(['retry_count', 'last_retry_at']);
        });
    }
};
