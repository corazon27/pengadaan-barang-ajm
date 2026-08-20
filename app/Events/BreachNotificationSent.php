<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\BreachNotification;
use App\Models\IncidentRegister;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BreachNotificationSent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly BreachNotification $notification,
        public readonly IncidentRegister $incident,
    ) {}
}
