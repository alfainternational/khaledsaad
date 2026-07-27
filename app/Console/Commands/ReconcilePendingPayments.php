<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Payments\CheckoutService;
use Illuminate\Console\Command;

class ReconcilePendingPayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes=15 : Minimum pending age} {--limit=100 : Maximum rows per run}';

    protected $description = 'Verify stale electronic payments against their original provider';

    public function handle(CheckoutService $checkout): int
    {
        $payments = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->where('provider', '!=', 'manual')
            ->whereNotNull('external_id')
            ->where('created_at', '<=', now()->subMinutes(max(1, (int) $this->option('minutes'))))
            ->oldest('id')->limit(min(500, max(1, (int) $this->option('limit'))))->get();

        $paid = 0;
        foreach ($payments as $payment) {
            try {
                $paid += $checkout->complete($payment, []) ? 1 : 0;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $this->info("Reconciled {$payments->count()} pending payments; {$paid} completed.");

        return self::SUCCESS;
    }
}
