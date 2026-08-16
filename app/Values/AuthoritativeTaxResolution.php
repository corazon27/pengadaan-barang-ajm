<?php

declare(strict_types=1);

namespace App\Values;

use App\Enums\TaxResolutionState;
use App\Exceptions\TaxResolutionNotAuthoritativeException;
use App\Models\RuleSnapshot;
use Illuminate\Support\Carbon;

/**
 * Immutable result of an authoritative tax resolution (Phase 2C).
 *
 * Distinct from TaxResolutionService (the orchestrator): this value object is
 * the resolved outcome. When state is RESOLVED, ruleSnapshot holds the
 * persisted immutable snapshot and the resolution was authoritative; otherwise
 * no snapshot was created and requireAuthoritative() throws.
 */
final readonly class AuthoritativeTaxResolution
{
    public function __construct(
        public TaxResolutionState $state,
        public TaxRuleResolution $resolution,
        public CommercialTaxContext $context,
        public Carbon $eventDate,
        public ?RuleSnapshot $ruleSnapshot = null,
    ) {}

    public function isAuthoritative(): bool
    {
        return $this->state === TaxResolutionState::RESOLVED && $this->ruleSnapshot !== null;
    }

    public function requiresReview(): bool
    {
        return ! $this->isAuthoritative();
    }

    public function requireAuthoritative(): RuleSnapshot
    {
        if (! $this->isAuthoritative()) {
            throw new TaxResolutionNotAuthoritativeException(
                $this->state,
                $this->resolution->reasonCode,
                "Authoritative tax resolution required but state is {$this->state->value} "
                    ."(reason: {$this->resolution->reasonCode}).",
            );
        }

        return $this->ruleSnapshot;
    }
}
