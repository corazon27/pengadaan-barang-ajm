<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AuditAction;
use App\Enums\B2gEvidenceStatus;
use App\Models\B2gEvidenceSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class B2gReviewTest extends TestCase
{
    use RefreshDatabase;

    private function confirmedAs(User $admin): static
    {
        return $this->withSession(['auth.password_confirmed_at' => time()])->actingAs($admin);
    }

    public function test_applicant_upload_creates_version_one(): void
    {
        Storage::fake('documents');
        $user = User::factory()->pending()->create();

        $url = URL::temporarySignedRoute('application.evidence.store', now()->addHour(), [
            'user' => $user->getKey(),
        ]);

        $this->post($url, [
            'evidence_type' => 'kontrak',
            'file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ])->assertRedirect(route('login'));

        $submission = B2gEvidenceSubmission::where('user_id', $user->getKey())->firstOrFail();
        $this->assertSame(1, $submission->version);
        $this->assertSame(B2gEvidenceStatus::SUBMITTED, $submission->status);
        Storage::disk('documents')->assertExists($submission->path);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::B2G_EVIDENCE_SUBMITTED->value,
            'entity_id' => $submission->getKey(),
        ]);
    }

    public function test_resubmission_supersedes_the_previous_version(): void
    {
        Storage::fake('documents');
        $user = User::factory()->pending()->create();

        $url = URL::temporarySignedRoute('application.evidence.store', now()->addHour(), [
            'user' => $user->getKey(),
        ]);

        $this->post($url, [
            'evidence_type' => 'kontrak',
            'file' => UploadedFile::fake()->create('v1.pdf', 100, 'application/pdf'),
        ]);

        $this->post($url, [
            'evidence_type' => 'kontrak',
            'file' => UploadedFile::fake()->create('v2.pdf', 100, 'application/pdf'),
        ]);

        $this->assertSame(2, B2gEvidenceSubmission::where('user_id', $user->getKey())->count());
        $latest = $user->fresh()->latestAcceptedB2gEvidence();
        $this->assertNull($latest);

        $this->assertDatabaseHas('b2g_evidence_submissions', [
            'user_id' => $user->getKey(),
            'version' => 1,
            'status' => B2gEvidenceStatus::SUPERSEDED->value,
        ]);
    }

    public function test_review_accepts_a_submission(): void
    {
        $admin = User::factory()->superadmin()->create();
        $target = User::factory()->pending()->create();
        $submission = B2gEvidenceSubmission::create([
            'user_id' => $target->getKey(),
            'version' => 1,
            'path' => 'b2g/evidence/'.$target->getKey().'/bukti.pdf',
            'original_filename' => 'bukti.pdf',
            'mime' => 'application/pdf',
            'size' => 1024,
            'evidence_type' => 'kontrak',
            'status' => B2gEvidenceStatus::SUBMITTED,
        ]);

        $this->confirmedAs($admin)
            ->post("/admin/bukti/{$submission->getKey()}/tinjau", [
                'decision' => B2gEvidenceStatus::ACCEPTED->value,
                'reason' => 'Valid.',
            ])->assertRedirect();

        $fresh = $submission->fresh();
        $this->assertSame(B2gEvidenceStatus::ACCEPTED, $fresh->status);
        $this->assertSame($admin->getKey(), $fresh->reviewed_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::B2G_EVIDENCE_REVIEWED->value,
            'entity_id' => $submission->getKey(),
        ]);
    }

    public function test_download_is_audited_before_streaming(): void
    {
        Storage::fake('documents');
        $admin = User::factory()->superadmin()->create();
        $target = User::factory()->pending()->create();
        $path = 'b2g/evidence/'.$target->getKey().'/bukti.pdf';
        Storage::disk('documents')->put($path, 'PDF-BYTES');

        $submission = B2gEvidenceSubmission::create([
            'user_id' => $target->getKey(),
            'version' => 1,
            'path' => $path,
            'original_filename' => 'bukti.pdf',
            'mime' => 'application/pdf',
            'size' => 9,
            'evidence_type' => 'kontrak',
            'status' => B2gEvidenceStatus::SUBMITTED,
        ]);

        $this->confirmedAs($admin)
            ->get("/admin/bukti/{$submission->getKey()}/unduh")
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::B2G_EVIDENCE_ACCESSED->value,
            'entity_id' => $submission->getKey(),
            'user_id' => $admin->getKey(),
        ]);
    }
}
