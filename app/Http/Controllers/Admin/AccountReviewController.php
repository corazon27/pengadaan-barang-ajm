<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\B2gEvidenceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveAccountRequest;
use App\Http\Requests\Admin\ReasonedAccountActionRequest;
use App\Http\Requests\Admin\ReviewB2gEvidenceRequest;
use App\Models\B2gEvidenceSubmission;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\AccountLifecycleService;
use App\Services\B2gEvidenceService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SUPERADMIN-only account review surface. Every action is authorized through
 * {@see UserPolicy}; high-impact actions also sit behind the
 * framework `password.confirm` middleware (route level).
 */
class AccountReviewController extends Controller
{
    public function index(): Response
    {
        $this->authorize('reviewApplications', User::class);

        $applications = User::query()
            ->where('status', UserStatus::PENDING)
            ->orderBy('created_at')
            ->get(['id', 'email', 'full_name', 'company_name', 'requested_role', 'created_at'])
            ->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'email' => $user->email,
                'full_name' => $user->full_name,
                'company_name' => $user->company_name,
                'requested_role' => $user->requested_role?->value,
                'created_at' => $user->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Accounts/Index', [
            'applications' => $applications,
        ]);
    }

    public function show(User $account): Response
    {
        $this->authorize('reviewApplications', User::class);

        $evidence = $account->b2gEvidenceSubmissions()
            ->get()
            ->map(fn (B2gEvidenceSubmission $submission): array => [
                'id' => $submission->getKey(),
                'version' => $submission->version,
                'evidence_type' => $submission->evidence_type,
                'institution_name' => $submission->institution_name,
                'work_unit' => $submission->work_unit,
                'position' => $submission->position,
                'reference' => $submission->reference,
                'original_filename' => $submission->original_filename,
                'status' => $submission->status->value,
                'review_reason' => $submission->review_reason,
                'created_at' => $submission->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Accounts/Show', [
            'account' => [
                'id' => $account->getKey(),
                'email' => $account->email,
                'full_name' => $account->full_name,
                'company_name' => $account->company_name,
                'npwp_number' => $account->npwp_number,
                'phone_number' => $account->phone_number,
                'address' => $account->address,
                'role' => $account->role->value,
                'status' => $account->status->value,
                'requested_role' => $account->requested_role?->value,
                'email_verified' => $account->hasVerifiedEmail(),
                'status_reason' => $account->status_reason,
                'status_changed_at' => $account->status_changed_at?->toIso8601String(),
                'created_at' => $account->created_at?->toIso8601String(),
            ],
            'evidence' => $evidence,
        ]);
    }

    public function approve(ApproveAccountRequest $request, User $account, AccountLifecycleService $service): RedirectResponse
    {
        $this->authorize('approve', $account);

        $service->approve(
            $request->user(),
            $account,
            UserRole::from($request->validated('role')),
            $request->validated('reason'),
        );

        return back()->with('status', 'Akun berhasil disetujui.');
    }

    public function reject(ReasonedAccountActionRequest $request, User $account, AccountLifecycleService $service): RedirectResponse
    {
        $this->authorize('reject', $account);

        $service->reject($request->user(), $account, $request->validated('reason'));

        return back()->with('status', 'Pengajuan ditolak.');
    }

    public function suspend(ReasonedAccountActionRequest $request, User $account, AccountLifecycleService $service): RedirectResponse
    {
        $this->authorize('suspend', $account);

        $service->suspend($request->user(), $account, $request->validated('reason'));

        return back()->with('status', 'Akun ditangguhkan.');
    }

    public function reactivate(ReasonedAccountActionRequest $request, User $account, AccountLifecycleService $service): RedirectResponse
    {
        $this->authorize('reactivate', $account);

        $service->reactivate($request->user(), $account, $request->validated('reason'));

        return back()->with('status', 'Akun diaktifkan kembali.');
    }

    public function reviewEvidence(ReviewB2gEvidenceRequest $request, B2gEvidenceSubmission $submission, B2gEvidenceService $service): RedirectResponse
    {
        $this->authorize('reviewB2g', $submission->user);

        $service->review(
            $request->user(),
            $submission,
            B2gEvidenceStatus::from($request->validated('decision')),
            $request->validated('reason'),
        );

        return back()->with('status', 'Tinjauan bukti tersimpan.');
    }
}
