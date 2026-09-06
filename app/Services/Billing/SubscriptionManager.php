<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
use App\Support\Billing\FeatureKey;
use Illuminate\Support\Facades\DB;

/**
 * إدارة اشتراك مساحة العمل وربطه بالأرصدة.
 *
 * الاشتراك يحدد حد المشاريع والرصيد الشهري. التبديل بين الخطط يمنح فرق
 * الرصيد مباشرة عبر CreditManager فتبقى محفظة واحدة مصدرًا للحقيقة.
 */
class SubscriptionManager
{
    public function __construct(private readonly CreditManager $credits) {}

    /**
     * ضمان وجود اشتراك للمساحة، بالخطة المجانية افتراضيًا.
     */
    public function ensure(Workspace $workspace): Subscription
    {
        $existing = $workspace->subscription()->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->subscribe($workspace, $this->freePlan());
    }

    public function subscribe(
        Workspace $workspace,
        Plan $plan,
        ?Payment $payment = null,
        bool $grantCredits = true,
        string $source = 'customer',
    ): Subscription {
        return DB::transaction(function () use ($workspace, $plan, $payment, $grantCredits, $source): Subscription {
            $subscription = Subscription::where('workspace_id', $workspace->id)->lockForUpdate()->first();
            $isNew = $subscription === null;
            $planChanged = $subscription !== null && $subscription->plan_id !== $plan->id;
            $newPaidPeriod = $payment !== null && $subscription?->last_payment_id !== $payment->id;
            $periodEnd = $plan->isFree() ? null : now()->addMonth();

            $subscription ??= new Subscription(['workspace_id' => $workspace->id]);
            $subscription->fill([
                'plan_id' => $plan->id,
                'status' => 'active',
                'renews_at' => $periodEnd,
                'ends_at' => null,
                'current_period_starts_at' => now(),
                'current_period_ends_at' => $periodEnd,
                'cancel_at_period_end' => false,
                'scheduled_plan_id' => null,
                'scheduled_change_at' => null,
                'scheduled_credit_policy' => null,
                'source' => $source,
                'last_payment_id' => $payment?->id ?? $subscription->last_payment_id,
                'suspended_at' => null,
            ])->save();

            // لا تمنح إعادة إرسال الطلب نفسه أو إعادة اختيار الخطة الحالية رصيدًا.
            if ($grantCredits && ($isNew || $planChanged || $newPaidPeriod) && $plan->monthly_credits > 0) {
                // مفتاحه الفترة لا اللحظة: تكرار مهمة التجديد داخل الفترة
                // نفسها لا يمنح رصيدًا ثانيًا، وبداية فترة جديدة تمنحه.
                $this->credits->grant(
                    $workspace,
                    $plan->monthly_credits,
                    "رصيد خطة {$plan->name}",
                    "grant:plan:{$workspace->id}:{$plan->id}:".$subscription->current_period_starts_at?->format('Y-m-d'),
                );
            }

            return $subscription;
        });
    }

    public function currentPlan(Workspace $workspace): Plan
    {
        return $this->ensure($workspace)->plan;
    }

    /**
     * حد المشاريع من عنصر الميزة إن ضُبط، وإلا من عمود الخطة القديم.
     * (يُحلّ Entitlements كسولًا لأنه يعتمد على هذه الخدمة نفسها.)
     */
    public function projectLimit(Workspace $workspace): int
    {
        $plan = $this->currentPlan($workspace);
        $limit = app(Entitlements::class)->planLimit($plan, FeatureKey::PROJECTS_LIMIT);

        // null = بلا حد.
        if ($limit === null) {
            return PHP_INT_MAX;
        }

        // صفر يعني أن الآدمن أغلق العنصر؛ لا نصفّر المشاريع لأن منتجًا بلا
        // مشروع واحد لا معنى له، فنرجع لعمود الخطة.
        return $limit > 0 ? $limit : $plan->project_limit;
    }

    /**
     * هل تستطيع المساحة إنشاء مشروع جديد ضمن حد خطتها؟
     */
    public function canCreateProject(Workspace $workspace): bool
    {
        return $workspace->projects()->count() < $this->projectLimit($workspace);
    }

    /**
     * الخطة المجانية. تُنشأ إن لم توجد حتى لا يعتمد إنشاء أي مساحة عمل على
     * بذر مسبق للخطط — فيبقى النظام صالحًا في أي بيئة.
     */
    private function freePlan(): Plan
    {
        return Plan::where('key', 'free')->first()
            ?? Plan::orderBy('price')->first()
            ?? Plan::create([
                'key' => 'free',
                'name' => 'مجانية',
                'price' => 0,
                'monthly_credits' => 5,
                'project_limit' => 1,
                'sort_order' => 1,
                'features' => ['أداة تشخيص واحدة', 'مشروع واحد'],
            ]);
    }
}
