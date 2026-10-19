<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\Rfq;
use App\Models\RfqItem;
use App\Models\User;
use App\Notifications\RfqSubmittedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Single writer for buyer RFQ submissions, shared by the Sanctum API and the
 * Inertia web flow so both paths produce identically shaped records.
 *
 * The RFQ, its items and the mandatory `RFQ_CREATED` audit row are written in a
 * single transaction through the strict audit path: if the audit entry cannot be
 * persisted, the entire submission rolls back. Superadmin notifications are only
 * dispatched after a successful commit.
 */
class RfqSubmissionService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array{notes?: string|null, items: array<int, array{product_id: string, quantity: int, target_price?: mixed, notes?: string|null}>}  $attributes
     */
    public function submit(User $user, array $attributes): Rfq
    {
        $items = $attributes['items'];
        $rfqNumber = UniqueIdentifier::generate('RFQ', Rfq::class, 'rfq_number');

        $rfq = DB::transaction(function () use ($user, $attributes, $items, $rfqNumber): Rfq {
            $rfq = Rfq::create([
                'rfq_number' => $rfqNumber,
                'user_id' => $user->id,
                'notes' => $attributes['notes'] ?? null,
            ]);

            foreach ($items as $item) {
                RfqItem::create([
                    'rfq_id' => $rfq->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'target_price' => $item['target_price'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $rfq->load('items.product', 'user');

            $this->auditLogger->strictLog($user, AuditAction::RFQ_CREATED, $rfq);

            return $rfq;
        });

        Notification::send(
            User::where('role', UserRole::SUPERADMIN)->get(),
            new RfqSubmittedNotification($rfq)
        );

        return $rfq;
    }
}
