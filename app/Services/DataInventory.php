<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LawfulBasis;

/**
 * Maps processing-class → underlying processing lawful basis.
 * For 3B.2: config-driven minimal mapping.
 * Full data_inventory_repository will be implemented in 3C.1.
 */
class DataInventory
{
    /**
     * Known processing-class → lawful-basis mappings.
     *
     * @var array<string, array{basis: string, rule_id: string}>
     */
    private const MAPPINGS = [
        'identity' => ['basis' => LawfulBasis::CONTRACT->value, 'rule_id' => 'RULE-DI-001'],
        'communication' => ['basis' => LawfulBasis::CONTRACT->value, 'rule_id' => 'RULE-DI-002'],
        'procurement' => ['basis' => LawfulBasis::CONTRACT->value, 'rule_id' => 'RULE-DI-003'],
        'financial' => ['basis' => LawfulBasis::LEGAL_OBLIGATION->value, 'rule_id' => 'RULE-DI-004'],
        'analytics' => ['basis' => LawfulBasis::LEGITIMATE_INTEREST->value, 'rule_id' => 'RULE-DI-005'],
        'marketing' => ['basis' => LawfulBasis::CONSENT->value, 'rule_id' => 'RULE-DI-006'],
        'vendor_management' => ['basis' => LawfulBasis::CONTRACT->value, 'rule_id' => 'RULE-DI-007'],
    ];

    /**
     * Resolve the underlying lawful basis for a given processing class.
     */
    public function resolve(string $processingClass): ?string
    {
        $mapping = self::MAPPINGS[$processingClass] ?? null;

        return $mapping['basis'] ?? null;
    }

    /**
     * Resolve the rule_id for a given processing class.
     */
    public function ruleId(string $processingClass): ?string
    {
        $mapping = self::MAPPINGS[$processingClass] ?? null;

        return $mapping['rule_id'] ?? null;
    }

    /**
     * Return all known processing classes.
     *
     * @return array<int, string>
     */
    public function knownClasses(): array
    {
        return array_keys(self::MAPPINGS);
    }
}
