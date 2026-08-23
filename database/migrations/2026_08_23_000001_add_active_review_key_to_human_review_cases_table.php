<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('human_review_cases', function (Blueprint $table) {
            $table->string('active_review_key')->nullable()->after('type');
        });

        // Backfill existing active cases with deterministic key
        $cases = DB::table('human_review_cases')
            ->whereIn('status', ['PENDING', 'REQUEST_MORE_EVIDENCE', 'ESCALATED'])
            ->whereNull('active_review_key')
            ->get();

        foreach ($cases as $case) {
            DB::table('human_review_cases')
                ->where('id', $case->id)
                ->update(['active_review_key' => "{$case->type}:{$case->subject_type}:{$case->subject_id}"]);
        }

        Schema::table('human_review_cases', function (Blueprint $table) {
            $table->unique('active_review_key', 'hrc_active_review_unique');
        });
    }

    public function down(): void
    {
        Schema::table('human_review_cases', function (Blueprint $table) {
            $table->dropIndex('hrc_active_review_unique');
            $table->dropColumn('active_review_key');
        });
    }
};
