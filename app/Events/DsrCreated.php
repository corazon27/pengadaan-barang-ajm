<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\DataSubjectRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DsrCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly DataSubjectRequest $dsr,
    ) {}
}
