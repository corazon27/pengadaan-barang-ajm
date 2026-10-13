<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\B2gEvidenceStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\B2gEvidenceSubmission;
use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use App\Notifications\NewAccountApplicationNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns every account lifecycle transition. Each critical state change and its
 * mandatory audit row are written inside ONE database transaction via
 * {@see AuditLogger::strictLog()}: if the audit row cannot be persisted the
 * change rolls back. Notifications are dispatched only after commit and are
 * best-effort, so a delivery failure can never invalidate a committed decision.
 */
class AccountLifecycleService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * Create a self-registered account in PENDING state.
     *
     * The actual role is always BUYER_B2B; `requested_role` records intent only
     * and never grants a permission. `requested_role` may be BUYER_B2G, which
     * the administrator must later satisfy with evidence before granting.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(array $data): User
    {
        $requestedRole = $data['requested_role'] ?? null;
        unset($data['requested_role']);

        if ($requestedRole !== null && $requestedRole !== UserRole::BUYER_B2G->value) {
            $requestedRole = null;
        }

        $user = DB::transaction(function () use ($data, $requestedRole): User {
            $user = new User($data);
            $user->requested_role = $requestedRole;
            $user->save();

            $this->auditLogger->strictLog($user, AuditAction::ACCOUNT_REGISTERED, $user, [], [
                'status' => UserStatus::PENDING->value,
                'requested_role' => $requestedRole,
                'role' => UserRole::BUYER_B2B->value,
            ]);

            return $user;
        });

        $this->notifyAdminsOfNewApplication($user);

        return $user;
    }

    /**
     * Approve a verified PENDING application. Granting BUYER_B2G requires an
     * ACCEPTED evidence submission; when the applicant requested B2G but the
     * administrator chooses B2B, the explicit choice is recorded and the
     * requested_role is cleared intentionally (never silently).
     */
    public function approve(User $actor, User $target, UserRole $grantRole, string $reason): User
    {
        if (! in_array($grantRole, [UserRole::BUYER_B2B, UserRole::BUYER_B2G], true)) {
            throw ValidationException::withMessages([
                'role' => 'Peran yang dapat disetujui hanya BUYER_B2B atau BUYER_B2G.',
            ]);
        }

        $approved = DB::transaction(function () use ($actor, $target, $grantRole, $reason): User {
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== UserStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan berstatus PENDING yang dapat disetujui.',
                ]);
            }

            if (! $locked->hasVerifiedEmail()) {
                throw ValidationException::withMessages([
                    'email' => 'Email belum diverifikasi; akun tidak dapat disetujui.',
                ]);
            }

            if ($grantRole === UserRole::BUYER_B2G) {
                $accepted = B2gEvidenceSubmission::query()
                    ->where('user_id', $locked->getKey())
                    ->where('status', B2gEvidenceStatus::ACCEPTED->value)
                    ->exists();

                if (! $accepted) {
                    throw ValidationException::withMessages([
                        'role' => 'Peran BUYER_B2G memerlukan bukti yang telah disetujui (ACCEPTED).',
                    ]);
                }
            }

            $previous = [
                'status' => $locked->status->value,
                'role' => $locked->role->value,
                'requested_role' => $locked->requested_role?->value,
            ];
            $locked->forceFill([
                'role' => $grantRole,
                'status' => UserStatus::ACTIVE,
                'status_reason' => $reason,
                'status_changed_at' => now(),
                'requested_role' => null,
            ])->save();

            $this->auditLogger->strictLog($actor, AuditAction::ACCOUNT_APPROVED, $locked, $previous, [
                'status' => UserStatus::ACTIVE->value,
                'role' => $grantRole->value,
                'decision_reason' => $reason,
            ]);

            return $locked;
        });

        $this->notifyStatus($approved, UserStatus::ACTIVE, $grantRole->value);

        return $approved;
    }

    public function reject(User $actor, User $target, string $reason): User
    {
        $rejected = DB::transaction(function () use ($actor, $target, $reason): User {
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== UserStatus::PENDING) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya pengajuan berstatus PENDING yang dapat ditolak.',
                ]);
            }

            $locked->forceFill([
                'status' => UserStatus::REJECTED,
                'status_reason' => $reason,
                'status_changed_at' => now(),
            ])->save();

            $this->auditLogger->strictLog($actor, AuditAction::ACCOUNT_REJECTED, $locked, [
                'status' => UserStatus::PENDING->value,
            ], [
                'status' => UserStatus::REJECTED->value,
                'decision_reason' => $reason,
            ]);

            return $locked;
        });

        $this->notifyStatus($rejected, UserStatus::REJECTED, null);

        return $rejected;
    }

    /**
     * Suspend an account. Revokes Sanctum tokens, deletes database-backed
     * browser sessions, and clears the remember token so a stale session cannot
     * be resurrected after reactivation. The last-active-superadmin invariant is
     * enforced under a serializing row lock.
     */
    public function suspend(User $actor, User $target, string $reason): User
    {
        $suspended = DB::transaction(function () use ($actor, $target, $reason): User {
            // Consistent locking convention: any operation that can change the
            // active-superadmin set locks that set FOR UPDATE first.
            $activeSuperadmins = $this->lockActiveSuperadmins();

            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->is($actor)) {
                throw ValidationException::withMessages([
                    'account' => 'Anda tidak dapat menangguhkan akun Anda sendiri.',
                ]);
            }

            if ($locked->status !== UserStatus::ACTIVE) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya akun AKTIF yang dapat ditangguhkan.',
                ]);
            }

            if ($locked->role === UserRole::SUPERADMIN) {
                $activeCount = $activeSuperadmins
                    ->where('status', UserStatus::ACTIVE)
                    ->where('id', '!=', $locked->getKey())
                    ->count();

                if ($activeCount < 1) {
                    throw ValidationException::withMessages([
                        'account' => 'Superadmin aktif terakhir tidak dapat ditangguhkan.',
                    ]);
                }
            }

            $locked->forceFill([
                'status' => UserStatus::SUSPENDED,
                'status_reason' => $reason,
                'status_changed_at' => now(),
                'remember_token' => null,
            ])->save();

            $locked->tokens()->delete();
            DB::table('sessions')->where('user_id', $locked->getKey())->delete();

            $this->auditLogger->strictLog($actor, AuditAction::ACCOUNT_SUSPENDED, $locked, [
                'status' => UserStatus::ACTIVE->value,
            ], [
                'status' => UserStatus::SUSPENDED->value,
                'decision_reason' => $reason,
                'tokens_revoked' => true,
                'sessions_invalidated' => true,
            ]);

            return $locked;
        });

        $this->notifyStatus($suspended, UserStatus::SUSPENDED, null);

        return $suspended;
    }

    public function reactivate(User $actor, User $target, string $reason): User
    {
        $reactivated = DB::transaction(function () use ($actor, $target, $reason): User {
            $this->lockActiveSuperadmins();

            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== UserStatus::SUSPENDED) {
                throw ValidationException::withMessages([
                    'status' => 'Hanya akun SUSPENDED yang dapat diaktifkan kembali.',
                ]);
            }

            $locked->forceFill([
                'status' => UserStatus::ACTIVE,
                'status_reason' => $reason,
                'status_changed_at' => now(),
            ])->save();

            $this->auditLogger->strictLog($actor, AuditAction::ACCOUNT_REACTIVATED, $locked, [
                'status' => UserStatus::SUSPENDED->value,
            ], [
                'status' => UserStatus::ACTIVE->value,
                'decision_reason' => $reason,
            ]);

            return $locked;
        });

        $this->notifyStatus($reactivated, UserStatus::ACTIVE, $reactivated->role->value);

        return $reactivated;
    }

    /**
     * Record a guest verification of an email address. Idempotent and scoped to
     * the verification timestamp only: it never changes status, role, or grants
     * access. Returns true when a change was made.
     */
    public function markEmailVerified(User $target): bool
    {
        $changed = DB::transaction(function () use ($target): bool {
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->hasVerifiedEmail()) {
                return false;
            }

            $locked->forceFill(['email_verified_at' => now()])->save();

            $this->auditLogger->strictLog($locked, AuditAction::EMAIL_VERIFIED, $locked, [], [
                'email_verified_at' => $locked->email_verified_at?->toIso8601String(),
            ]);

            return true;
        });

        return $changed;
    }

    /**
     * Guest resubmission of a REJECTED application. Returns the account to
     * PENDING without granting any operational access. Returns true when applied.
     *
     * @param  array<string, mixed>  $data
     */
    public function resubmit(User $target, array $data): bool
    {
        $requestedRole = $data['requested_role'] ?? null;
        if ($requestedRole !== null && $requestedRole !== UserRole::BUYER_B2G->value) {
            $requestedRole = null;
        }

        $applied = DB::transaction(function () use ($target, $requestedRole): bool {
            $locked = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== UserStatus::REJECTED) {
                return false;
            }

            $locked->forceFill([
                'status' => UserStatus::PENDING,
                'status_reason' => null,
                'status_changed_at' => now(),
                'requested_role' => $requestedRole ?? $locked->requested_role,
            ])->save();

            $this->auditLogger->strictLog($locked, AuditAction::APPLICATION_RESUBMITTED, $locked, [
                'status' => UserStatus::REJECTED->value,
            ], [
                'status' => UserStatus::PENDING->value,
                'requested_role' => $locked->requested_role,
            ]);

            return true;
        });

        return $applied;
    }

    /**
     * @return Collection<int, User>
     */
    private function lockActiveSuperadmins(): Collection
    {
        return User::query()
            ->where('role', UserRole::SUPERADMIN)
            ->where('status', UserStatus::ACTIVE)
            ->lockForUpdate()
            ->get();
    }

    private function notifyAdminsOfNewApplication(User $applicant): void
    {
        $this->safeNotify(function () use ($applicant): void {
            User::query()
                ->where('role', UserRole::SUPERADMIN)
                ->where('status', UserStatus::ACTIVE)
                ->get()
                ->each(fn (User $admin) => $admin->notify(new NewAccountApplicationNotification($applicant)));
        });
    }

    private function notifyStatus(User $user, UserStatus $status, ?string $role): void
    {
        $this->safeNotify(fn () => $user->notify(
            new AccountStatusChangedNotification($status, $role, $user->status_reason)
        ));
    }

    private function safeNotify(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
