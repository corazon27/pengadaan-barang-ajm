<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\BastDocument;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\PSECertificate;
use App\Models\PSERegistration;
use App\Models\RegulatoryReference;
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
        OrderItem::class => [
            'order_id',
            'product_id',
            'quantity',
            'unit_price_snapshot',
            'line_base_amount_snapshot',
            'product_classification_snapshot',
            'buyer_classification_snapshot',
            'collector_status_snapshot',
            'transaction_type_snapshot',
            'taxpayer_status_snapshot',
            'order_time_rule_id',
            'order_time_rule_code',
            'order_time_rule_version',
            'commercial_context_frozen_at',
        ],
        BastDocument::class => ['status', 'signed_at', 'signed_date', 'notes'],
        Invoice::class => ['status', 'amount_due', 'grand_total', 'paid_at', 'subtotal', 'ppn_amount', 'tax_calculation_version'],
        Payment::class => ['status', 'amount', 'verified_at', 'rejection_reason'],
        User::class => ['full_name', 'email', 'company_name', 'role'],
        Product::class => ['sku', 'title', 'base_price', 'stock', 'is_sni'],
        RegulatoryReference::class => ['reference_code', 'title', 'effective_from', 'effective_to', 'source_version', 'status'],
        PSERegistration::class => [
            'pse_registration_number',
            'pse_type',
            'registered_at',
            'maintenance_due_at',
            'registration_status',
            'applicability',
        ],
        PSECertificate::class => [
            'certificate_number',
            'psre_provider',
            'issued_at',
            'expires_at',
            'certificate_status',
            'verification_status',
        ],
    ];

    /**
     * Persist an audit log entry for a critical state change. Failures are
     * reported and swallowed so auditing never aborts the business action.
     *
     * The entity is optional: authentication events (login/logout) that
     * predate a resolved record pass no entity, and the row is still written.
     */
    public function log(
        ?User $user,
        AuditAction $action,
        ?Model $entity = null,
        ?array $previousState = null,
        ?array $newState = null,
    ): ?AuditLog {
        try {
            return DB::transaction(function () use ($user, $action, $entity, $previousState, $newState) {
                return AuditLog::create([
                    'user_id' => $user?->id,
                    'action' => $action->value,
                    'entity_type' => $entity ? class_basename($entity) : null,
                    'entity_id' => $entity?->getKey(),
                    'previous_state' => $previousState ?? ($entity ? $this->snapshot($entity) : []),
                    'new_state' => $newState ?? ($entity ? $this->snapshot($entity) : []),
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
