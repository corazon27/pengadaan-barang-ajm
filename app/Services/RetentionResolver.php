<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Resolves retention rules for processing classes.
 * 3B.2: returns unresolved (APPLICABILITY_UNKNOWN) for any class without
 * explicit legal duration. No invented durations per plan guardrails.
 * Full retention-rule repository will be implemented in 3C.1.
 */
class RetentionResolver
{
    /**
     * Resolve the retention duration for a processing class.
     * Returns null when no explicit rule is known (caller must set
     * applicability_status = APPLICABILITY_UNKNOWN).
     *
     * @return array{duration_days: int, rule_id: string, basis: string}|null
     */
    public function resolve(string $processingClass): ?array
    {
        return self::KNOWN[$processingClass] ?? null;
    }

    /**
     * All explicitly known retention rules (non-exhaustive).
     *
     * @var array<string, array{duration_days: int, rule_id: string, basis: string}>
     */
    private const KNOWN = [
        // BUKTI-05: Pajak fiskal retensi 10 tahun → tunda sampai legal review
        // UU ITE 2 tahun → tunda sampai legal review
        // Artinya: 3B.2 hanya resolve class yang DAPAT dipenuhi secara deterministik
        'financial' => [
            'duration_days' => 3650,
            'rule_id' => 'RULE-RET-001',
            'basis' => 'Tax regulation 10-year fiscal retention (PENDING_LEGAL_REVIEW — do not enforce until confirmed)',
        ],
    ];
}
