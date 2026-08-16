<?php

declare(strict_types=1);

use App\Enums\PseRegistrationApplicability;
use App\Enums\PseRegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3B.1 — PSE Registration Registry (PSE-REG-001/002/003).
     *
     * Registry-only governance record of the PSE (privat) registration with
     * the government (PP 71/2019 Ps 6(1); Permenkominfo 5/2020 jo 10/2021
     * Ps 4-6). AJM records externally obtained registration evidence; it does
     * NOT generate registration numbers.
     *
     * Lifecycle (registration_status) and internal legal applicability
     * (applicability) are intentionally separate columns: a REGISTERED record
     * does not itself establish legal applicability, and applicability
     * review never drives the external lifecycle.
     *
     * No FK to audit_logs; audit trail lives on the existing Module 8
     * audit_logs table via entity_type/entity_id metadata only.
     */
    public function up(): void
    {
        Schema::create('pse_registration', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pse_registration_number', 100)->nullable();
            $table->string('pse_type', 50)->default('PRIVAT');
            $table->date('registered_at')->nullable();
            $table->date('maintenance_due_at')->nullable();
            $table->enum('registration_status', array_column(PseRegistrationStatus::cases(), 'value'))
                ->default(PseRegistrationStatus::UNREGISTERED->value);
            $table->enum('applicability', array_column(PseRegistrationApplicability::cases(), 'value'))
                ->default(PseRegistrationApplicability::UNRESOLVED->value);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('registration_status');
            $table->index('applicability');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pse_registration');
    }
};
