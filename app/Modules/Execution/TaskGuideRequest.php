<?php

namespace App\Modules\Execution;

use App\Jobs\DevelopTaskGuide;
use App\Models\Task;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\QueryBudgetManager;

/**
 * طلب تطوير المهمة: الحجز ثم الطابور، بترتيب لا يُعكَس.
 *
 * موضع واحد يقرأ منه الويب والتطبيق حتى لا يوجد مساران بقاعدتين. لو تُرك
 * لكل متحكّم أن يحجز بنفسه لانحرف أحدهما يومًا وصار سطحٌ يستدعي النموذج
 * خارج السقف.
 *
 * حين ينفد السقف لا يُرفض الطلب: تُطوَّر المهمة بالأرضية الحتمية ويُعلَن
 * أن دليلها مبدئي. الرفض يترك المستخدم بلا شيء، والادعاء يخفي الفرق —
 * وكلاهما أسوأ من قالب مأمون معلَّم المصدر (§٤.٣).
 */
class TaskGuideRequest
{
    /** استعلام واحد لكل تطوير: طلب منظَّم واحد بمحاولات تصحيحه. */
    private const QUERIES_PER_GUIDE = 1;

    public function __construct(
        private readonly QueryBudgetManager $budgets,
    ) {}

    public function dispatch(Task $task): void
    {
        $workspace = $task->project?->workspace;
        $reservationId = null;

        if ($workspace !== null) {
            try {
                $reservationId = $this->budgets->reserve(
                    workspace: $workspace,
                    queries: self::QUERIES_PER_GUIDE,
                    purpose: 'task_guide',
                    project: $task->project,
                )->id;
            } catch (BudgetExhausted) {
                // يمرّ بلا حجز: العامل يكتب دليلًا حتميًّا بلا استدعاء واحد.
                $reservationId = null;
            }
        }

        $task->forceFill(['guide_status' => Task::GUIDE_PENDING])->save();

        DevelopTaskGuide::dispatch($task->id, $reservationId);
    }
}
