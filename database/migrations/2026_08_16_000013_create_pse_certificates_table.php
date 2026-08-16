<?php

declare(strict_types=1);

use App\Enums\PseCertificateStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3B.1 — PSE Electronic Certificate Registry (PSE-CERT-001).
     *
     * Registry of the Sertifikat Elektronik issued to AJM by PSrE Indonesia
     * (PP 71/2019 Ps 51(1),(3); Permenkominfo 11/2022 Ps 24(2), 25-26, 29(2)).
     *
     * AJM never issues certificates internally and never generates
     * certificate serials: certificate_number is operator-recorded external
     * evidence. certificate_status is the EXTERNAL certificate lifecycle;
     * verification_status is the INTERNAL verification state. The two are
     * kept strictly separate and neither is inferred from the other.
     *
     * Distinct from user TTE / BSrE / Sertifikat Keandalan — no conflation.
     */
    public function up(): void
    {
        Schema::create('pse_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('certificate_number', 100)->nullable();
            $table->string('psre_provider', 200)->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->enum('certificate_status', array_column(PseCertificateStatus::cases(), 'value'))
                ->default(PseCertificateStatus::PENDING->value);
            $table->enum('verification_status', array_column(VerificationStatus::cases(), 'value'))
                ->default(VerificationStatus::UNVERIFIED->value);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('certificate_status');
            $table->index('verification_status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pse_certificates');
    }
};
