<?php

namespace App\Services\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Workspace;
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

    public function subscribe(Workspace $workspace, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($workspace, $plan): Subscription {
            $subscription = Subscription::updateOrCreate(
                ['workspace_id' => $workspace->id],
                [
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'renews_at' => $plan->isFree() ? null : now()->addMonth(),
                    'ends_at' => null,
                ],
            );

            // منح رصيد الخطة عند الاشتراك، مرة واحدة لهذا التفعيل.
            if ($plan->monthly_credits > 0) {
                $this->credits->grant($workspace, $plan->monthly_credits, "رصيد خطة {$plan->name}");
            }

            return $subscription;
        });
    }

    public function currentPlan(Workspace $workspace): Plan
    {
        return $this->ensure($workspace)->plan;
    }

    public function projectLimit(Workspace $workspace): int
    {
        return $this->currentPlan($workspace)->project_limit;
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
