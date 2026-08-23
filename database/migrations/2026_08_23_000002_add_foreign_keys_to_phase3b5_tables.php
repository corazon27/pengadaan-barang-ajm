<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legal_function_assignments', function (Blueprint $table) {
            $table->dropIndex(['human_review_case_id']);
            $table->foreign('human_review_case_id')
                ->references('id')
                ->on('human_review_cases')
                ->onDelete('set null');
        });

        Schema::table('processor_agreements', function (Blueprint $table) {
            $table->dropIndex(['human_review_case_id']);
            $table->foreign('human_review_case_id')
                ->references('id')
                ->on('human_review_cases')
                ->onDelete('set null');

            $table->dropIndex(['actor_user_id']);
            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('legal_function_assignments', function (Blueprint $table) {
            $table->dropForeign(['human_review_case_id']);
            $table->index('human_review_case_id');
        });

        Schema::table('processor_agreements', function (Blueprint $table) {
            $table->dropForeign(['human_review_case_id']);
            $table->index('human_review_case_id');

            $table->dropForeign(['actor_user_id']);
            $table->index('actor_user_id');
        });
    }
};
