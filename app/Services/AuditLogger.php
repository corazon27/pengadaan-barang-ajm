<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\BastDocument;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    /**
     * State fields captured per entity type so snapshots stay small and
     * focused on the business-critical attributes.
     *
     * @var array<string, array<int, string>>
     */
    private const STATE_FIELDS = [
        Rfq::class => ['status', 'valid_until', 'admin_notes'],
        Order::class => ['status', 'total_amount', 'top_days'],
        BastDocument::class => ['status', 'signed_at', 'signed_date', 'notes'],
        Invoice::class => ['status', 'amount_due', 'grand_total', 'paid_at'],
        Payment::class => ['status', 'amount', 'verified_at', 'rejection_reason'],
    ];

    /**
     * Persist an audit log entry for a critical state change. Failures are
     * reported and swallowed so auditing never aborts the business action.
     */
    public function log(
        ?User $user,
        AuditAction $action,
        Model $entity,
        ?array $previousState = null,
        ?array $newState = null,
    ): ?AuditLog {
        try {
            return DB::transaction(function () use ($user, $action, $entity, $previousState, $newState) {
                return AuditLog::create([
                    'user_id' => $user?->id,
                    'action' => $action->value,
                    'entity_type' => class_basename($entity),
                    'entity_id' => $entity->getKey(),
                    'previous_state' => $previousState ?? $this->snapshot($entity),
                    'new_state' => $newState ?? $this->snapshot($entity),
                ]);
            });
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Capture a snapshot of the entity's auditable state fields.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Model $entity): array
    {
        $fields = self::STATE_FIELDS[$entity::class] ?? [];

        $snapshot = [];
        foreach ($fields as $field) {
            $value = $entity->getAttribute($field);

            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $value = $value->format(DATE_ATOM);
            }

            $snapshot[$field] = $value;
        }

        return $snapshot;
    }
}
