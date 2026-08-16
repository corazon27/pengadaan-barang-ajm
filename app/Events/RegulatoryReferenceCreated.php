<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\RegulatoryReference;
use Illuminate\Foundation\Events\Dispatchable;

class RegulatoryReferenceCreated
{
    use Dispatchable;

    public function __construct(
        public readonly RegulatoryReference $reference,
    ) {}
}
