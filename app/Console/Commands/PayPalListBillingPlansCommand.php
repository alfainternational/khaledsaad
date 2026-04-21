<?php

namespace App\Console\Commands;

use App\Services\Billing\PayPalService;
use Illuminate\Console\Command;

class PayPalListBillingPlansCommand extends Command
{
    protected $signature = 'paypal:list-plans {--page=1 : Page number (PayPal API)}';

    protected $description = 'عرض خطط اشتراك PayPal (معرّفات P-) لنسخها إلى .env أو الإدارة → الخطط';

    public function handle(PayPalService $paypal): int
    {
        if (! $paypal->isConfigured()) {
            $this->error('PayPal غير مُعدّ (مفاتيح ناقصة أو PAYPAL_ENABLED=false).');

            return self::FAILURE;
        }

        $page = max(1, (int) $this->option('page'));

        try {
            $payload = $paypal->listBillingPlans($page);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $plans = $payload['plans'] ?? [];
        $total = $payload['total_items'] ?? null;

        if ($total !== null) {
            $this->info('إجمالي الخطط (حسب PayPal): '.$total);
        }

        if ($plans === []) {
            $this->warn('لا توجد خطط في هذه الصفحة.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($plans as $row) {
            $rows[] = [
                'id' => $row['id'] ?? '',
                'name' => $row['name'] ?? '',
                'status' => $row['status'] ?? '',
            ];
        }

        $this->table(['Plan ID', 'Name', 'Status'], $rows);
        $this->line('');
        $this->comment('انسخ القيم إلى PAYPAL_PLAN_<CODE>_MONTHLY / _ANNUAL في .env أو إلى حقول الخطة في الإدارة.');
        $this->comment('لباقات starter و team: يكفي ضبط PAYPAL_PLAN_PRO_* إن كانت نفس منتجات PayPal.');

        return self::SUCCESS;
    }
}
