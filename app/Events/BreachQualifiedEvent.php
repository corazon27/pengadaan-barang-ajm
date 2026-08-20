<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BreachQualifiedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $sourceType,
        public readonly string $sourceId,
        public readonly \DateTime $detectedAt,
        public readonly string $ruleId,
    ) {}
}
