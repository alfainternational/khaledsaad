<?php

namespace App\Jobs;

use App\Application\Reports\BuildProjectReportAction;
use App\Domain\Project\Models\Project;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * يُدفّئ كاش التركيب الاستراتيجي (LLM) لتقرير المشروع في الخلفية.
 *
 * السبب: نداء LLM قد يحجب حتى 90 ثانية عند تعثّر المزوّد، وهو غير مقبول داخل
 * طلب ويب (ينتج 500). لذا يعرض الويب تقريراً محلياً فوراً ويُفوّض التركيب الذكي
 * لهذا الـ Job، فيظهر عند التحديث التالي. متوافق مع قاعدة «كل AI call يمر بـ Queue».
 */
class WarmProjectReportSynthesisJob implements ShouldQueue
{
    use Queueable;

    /** أقصى زمن للـ Job (نداء LLM قد يصل ~90ث + فسحة). */
    public int $timeout = 150;

    public int $tries = 1;

    public function __construct(
        public readonly int $projectId,
    ) {}

    public function handle(BuildProjectReportAction $action): void
    {
        $project = Project::query()->with('workspace')->find($this->projectId);
        if (! $project) {
            return;
        }

        // fresh=true + allowBlocking=true → يولّد التركيب الذكي ويخزّنه في الكاش.
        $action->handle($project, fresh: true, allowBlocking: true);
    }
}
