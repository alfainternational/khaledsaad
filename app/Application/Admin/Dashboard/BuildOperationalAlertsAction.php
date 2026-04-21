<?php

namespace App\Application\Admin\Dashboard;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Support\Facades\DB;

class BuildOperationalAlertsAction
{
    /**
     * @return list<array{severity: string, title: string, body: string, url: string}>
     */
    public function handle(): array
    {
        $alerts = [];

        $pastDue = Subscription::query()->where('status', 'past_due')->count();
        if ($pastDue > 0) {
            $alerts[] = [
                'severity' => 'danger',
                'title' => 'اشتراكات متأخرة السداد',
                'body' => sprintf('يوجد %d اشتراكاً بحالة past_due يتطلب متابعة فوترة.', $pastDue),
                'url' => route('admin.subscriptions.index', ['status' => 'past_due']),
            ];
        }

        $pendingPayment = Subscription::query()->where('status', 'pending_payment')->count();
        if ($pendingPayment > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'دفعات قيد الانتظار',
                'body' => sprintf('يوجد %d اشتراكاً بحالة pending_payment.', $pendingPayment),
                'url' => route('admin.subscriptions.index', ['status' => 'pending_payment']),
            ];
        }

        $needsInput = AIGeneration::query()
            ->where('status', 'needs_input')
            ->where('created_at', '>=', now()->subDays(7))
            ->where(function ($q): void {
                $q->whereNull('ops_review_status')
                    ->orWhereNotIn('ops_review_status', ['resolved', 'voided']);
            })
            ->count();

        if ($needsInput > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'مخرجات AI تحتاج مدخلات',
                'body' => sprintf('خلال 7 أيام: %d توليداً بحالة needs_input دون إغلاق تشغيلي.', $needsInput),
                'url' => route('admin.ai-generations.index', ['status' => 'needs_input']),
            ];
        }

        $recentGenerations = AIGeneration::query()->where('created_at', '>=', now()->subDay())->count();
        if ($recentGenerations >= 100) {
            $alerts[] = [
                'severity' => 'info',
                'title' => 'نشاط AI مرتفع',
                'body' => sprintf('تم تسجيل %d توليداً خلال 24 ساعة.', $recentGenerations),
                'url' => route('admin.ai-generations.index'),
            ];
        }

        $lowCreditAccounts = DB::table('ai_credits_ledger')
            ->select('account_id', DB::raw('SUM(delta) as balance'))
            ->groupBy('account_id')
            ->havingRaw('SUM(delta) < ?', [5])
            ->count();

        if ($lowCreditAccounts > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'title' => 'أرصدة AI منخفضة',
                'body' => sprintf('يوجد %d حساباً بمجموع رصيد أقل من 5.', $lowCreditAccounts),
                'url' => route('admin.ai-credits.index'),
            ];
        }

        $voidedToolRuns = DB::table('tool_runs')
            ->where('ops_review_status', 'voided')
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($voidedToolRuns > 0) {
            $alerts[] = [
                'severity' => 'info',
                'title' => 'عمليات أدوات ملغاة (مراجعة)',
                'body' => sprintf('آخر 30 يوماً: %d تشغيل أداة مُعلَّم voided.', $voidedToolRuns),
                'url' => route('admin.tool-runs.index', ['ops_review_status' => 'voided']),
            ];
        }

        return $alerts;
    }
}
