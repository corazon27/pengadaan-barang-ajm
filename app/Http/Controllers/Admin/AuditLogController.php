<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Policies\AdminPolicy;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SUPERADMIN read-only audit trail browser. Console gate via {@see AdminPolicy}
 * plus AuditLogPolicy@viewAny (Superadmin only).
 *
 * State snapshots are NEVER dumped verbatim. Only an explicit allowlist of
 * business field names is rendered, and only scalar values. Anything outside
 * the allowlist (including PII such as full_name/email/company_name and any
 * future field) is omitted and reported only as a redacted count. This keeps
 * passwords, tokens, raw NPWP, document contents, file paths, and other PII off
 * the page by construction.
 */
class AuditLogController extends Controller
{
    /**
     * Business fields that are safe and useful to display. Derived from the
     * AuditLogger state-field contract, minus personal identifiers.
     *
     * @var array<int, string>
     */
    private const SAFE_STATE_FIELDS = [
        'status', 'requested_role', 'role', 'valid_until', 'admin_notes',
        'total_amount', 'top_days', 'quantity', 'unit_price_snapshot',
        'line_base_amount_snapshot', 'product_classification_snapshot',
        'buyer_classification_snapshot', 'collector_status_snapshot',
        'transaction_type_snapshot', 'taxpayer_status_snapshot',
        'order_time_rule_id', 'order_time_rule_code', 'order_time_rule_version',
        'commercial_context_frozen_at', 'signed_at', 'signed_date', 'amount_due',
        'grand_total', 'paid_at', 'subtotal', 'ppn_amount',
        'tax_calculation_version', 'amount', 'verified_at', 'rejection_reason',
        'sku', 'title', 'base_price', 'stock', 'is_sni', 'reference_code',
        'effective_from', 'effective_to', 'source_version',
        'pse_registration_number', 'pse_type', 'registered_at',
        'maintenance_due_at', 'registration_status', 'applicability',
        'certificate_number', 'psre_provider', 'issued_at', 'expires_at',
        'certificate_status', 'verification_status', 'right_code', 'channel',
        'identity_verification_status', 'identity_confidence',
        'applicability_status', 'handled_by', 'purpose',
        'processing_lawful_basis', 'notice_version', 'document_ref',
        'consent_status', 'granted_at', 'withdrawn_at', 'source_channel',
        'rule_id', 'timer_type', 'enforcement', 'ref_type', 'ref_id',
        'started_at', 'deadline_at', 'violation_state', 'incident_type',
        'severity', 'breach_qualification_status', 'affected_systems',
        'affected_data_categories', 'number_of_subjects_known',
        'notification_type', 'sent_at', 'confirmed_at', 'failure_reason',
        'retry_count', 'function_code', 'function_category', 'statutory_basis',
        'appointment_basis', 'effective_until', 'scope', 'human_review_case_id',
        'processor_role_status', 'agreement_status', 'evidence_verified',
        'data_categories', 'processing_purposes', 'legal_basis',
    ];

    /**
     * Known entity basenames emitted by AuditLogger. Used to bound the filter.
     *
     * @var array<int, string>
     */
    private const ENTITY_TYPES = [
        'User', 'Product', 'Rfq', 'Order', 'OrderItem', 'BastDocument',
        'Invoice', 'Payment', 'RegulatoryReference', 'PSERegistration',
        'PSECertificate', 'DataSubjectRequest', 'ConsentRecord', 'StatutoryTimer',
        'IncidentRegister', 'BreachNotification', 'LegalFunctionAssignment',
        'ProcessorAgreement',
    ];

    public function index(Request $request): Response
    {
        $this->authorize('view', AdminPolicy::class);
        $this->authorize('viewAny', AuditLog::class);

        $entityType = $request->input('entity_type');
        $entityType = is_string($entityType) && in_array($entityType, self::ENTITY_TYPES, true)
            ? $entityType
            : null;

        $action = $request->input('action');
        $action = is_string($action) && AuditAction::tryFrom($action) !== null
            ? $action
            : null;

        $paginator = AuditLog::query()
            ->with('user:id,full_name')
            ->when($entityType !== null, fn ($query) => $query->where('entity_type', $entityType))
            ->when($action !== null, fn ($query) => $query->where('action', $action))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $logs = $paginator->map(fn (AuditLog $log): array => [
            'id' => $log->getKey(),
            'actor_name' => $log->user?->full_name,
            'action' => $log->action,
            'entity_type' => $log->entity_type,
            'entity_id' => $log->entity_id,
            'previous_state' => $this->presentState($log->previous_state),
            'new_state' => $this->presentState($log->new_state),
            'created_at' => $log->created_at?->toIso8601String(),
        ])->values();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'pagination' => $this->pagination($paginator, '/admin/audit'),
            'filters' => ['entity_type' => $entityType, 'action' => $action],
            'entityTypes' => self::ENTITY_TYPES,
            'actions' => array_map(fn (AuditAction $case): string => $case->value, AuditAction::cases()),
        ]);
    }

    /**
     * Reduce a raw state snapshot to an allowlisted, scalar-only projection.
     *
     * @param  array<string, mixed>|null  $state
     * @return array{fields: array<string, string|null>, redacted: int}
     */
    private function presentState(?array $state): array
    {
        $fields = [];
        $redacted = 0;

        foreach ($state ?? [] as $key => $value) {
            if (! in_array($key, self::SAFE_STATE_FIELDS, true) || ! is_scalar($value)) {
                $redacted++;

                continue;
            }

            $fields[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return ['fields' => $fields, 'redacted' => $redacted];
    }
}
