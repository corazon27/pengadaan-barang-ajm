<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes that cover the audit log listing query shapes:
     *
     *   1. AuditLog::query()->with('user')->latest()->paginate()           -> created_at
     *   2. + where('entity_type', $x)                                       -> entity_type + created_at
     *   3. + where('action', $x)                                            -> action + created_at
     *   4. + where('entity_type')->where('action')                          -> entity_type + action + created_at
     *
     * The existing (entity_type, entity_id) index remains for entity lookups.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['created_at']);
            $table->index(['entity_type', 'action', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['action', 'created_at']);
            $table->dropIndex(['entity_type', 'action', 'created_at']);
            $table->dropIndex(['created_at']);
        });
    }
};
