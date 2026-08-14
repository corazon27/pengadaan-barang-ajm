<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckOverdueInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark unpaid invoices past their due date as OVERDUE';

    public function handle(AuditLogger $auditLogger): int
    {
        $affected = 0;

        DB::transaction(function () use ($auditLogger, &$affected) {
            // Lock the qualifying rows inside the transaction so a concurrent
            // run cannot process (or re-process) the same invoices.
            $dueInvoices = Invoice::query()
                ->lockForUpdate()
                ->whereIn('status', [InvoiceStatus::UNPAID, InvoiceStatus::PARTIALLY_PAID])
                ->where('due_date', '<', now()->startOfDay())
                ->get();

            foreach ($dueInvoices as $invoice) {
                $previousState = $auditLogger->snapshot($invoice);

                $invoice->update(['status' => InvoiceStatus::OVERDUE]);

                $auditLogger->log(null, AuditAction::INVOICE_MARKED_OVERDUE, $invoice, $previousState);

                $affected++;
            }
        });

        $this->components->info("Marked {$affected} overdue invoice(s).");

        return Command::SUCCESS;
    }
}
