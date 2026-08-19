<?php

namespace App\Console\Commands;

use App\Models\BillingAudit;
use App\Models\Subscription;
use App\Services\Billing\CreditManager;
use App\Services\Billing\SubscriptionAssignmentService;
use App\Services\Billing\SubscriptionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * تنفيذ تغييرات الخطة المؤجَّلة إلى نهاية الفترة.
 *
 * كان `scheduled_plan_id` يُكتب ولا يقرأه أحد: يختار الآدمن «نهاية الفترة»
 * فلا تصل الترقية أبدًا. هذا الأمر هو الطرف الثاني للعقد — يطبّق الخطة
 * بمزاياها كاملة عند حلول موعدها، ويسجّل القرار في سجل الفوترة.
 */
class ApplyScheduledPlanChanges extends Command
{
    protected $signature = 'subscriptions:apply-scheduled {--limit=500 : Maximum subscriptions per run}';

    protected $description = 'Apply subscription plan changes that were scheduled for the end of the period';

    public function handle(SubscriptionManager $subscriptions, CreditManager $credits): int
    {
        $due = Subscription::query()
            ->whereNotNull('scheduled_plan_id')
            ->whereNotNull('scheduled_change_at')
            ->where('scheduled_change_at', '<=', now())
            ->with(['workspace', 'scheduledPlan'])
            ->oldest('scheduled_change_at')
            ->limit(min(2000, max(1, (int) $this->option('limit'))))
            ->get();

        $applied = 0;
        $failed = 0;

        foreach ($due as $subscription) {
            $workspace = $subscription->workspace;
            $plan = $subscription->scheduledPlan;

            // خطة محذوفة أو مساحة محذوفة: لا تُترك الصفوف معلّقة إلى الأبد.
            if ($workspace === null || $plan === null) {
                $subscription->forceFill([
                    'scheduled_plan_id' => null,
                    'scheduled_change_at' => null,
                    'scheduled_credit_policy' => null,
                ])->save();

                continue;
            }

            try {
                DB::transaction(function () use ($subscription, $workspace, $plan, $subscriptions, $credits): void {
                    $before = $subscription->only([
                        'plan_id', 'status', 'current_period_starts_at', 'current_period_ends_at',
                        'cancel_at_period_end', 'scheduled_plan_id', 'scheduled_change_at', 'source',
                    ]);

                    // الافتراضي منح رصيد الخطة: الباقة المؤجَّلة تصل بمزاياها
                    // كما لو طُبِّقت فورًا، وإلا صار موعد التنفيذ عقوبة.
                    $policy = $subscription->scheduled_credit_policy
                        ?: SubscriptionAssignmentService::DEFAULT_CREDIT_POLICY;

                    $fresh = $subscriptions->subscribe(
                        $workspace,
                        $plan,
                        null,
                        $policy === 'plan_grant',
                        $subscription->source ?: 'admin',
                    );

                    BillingAudit::create([
                        'actor_id' => null,
                        'workspace_id' => $workspace->id,
                        'action' => 'subscription.scheduled_applied',
                        'subject_type' => Subscription::class,
                        'subject_id' => $fresh->id,
                        'before' => $before,
                        'after' => $fresh->fresh()->only(array_keys($before)),
                        'metadata' => [
                            'target_plan_id' => $plan->id,
                            'credit_policy' => $policy,
                            'scheduled_for' => $before['scheduled_change_at'],
                        ],
                    ]);
                });

                $applied++;
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
            }
        }

        $this->info("Applied {$applied} scheduled plan changes; {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
