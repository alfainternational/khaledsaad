<?php

namespace App\Modules\AiReadiness\Jobs;

use App\Models\Project;
use App\Modules\AiReadiness\ReadinessCollector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * تدقيق موقع مشروع في الطابور.
 *
 * كل عملية خارجية داخل Job (§٨): جلب صفحات موقع قد يتأخر ثوانيَ أو يتعذّر،
 * وتعليق طلب المستخدم عليه يجعل الشاشة تبدو معطّلة بينما النظام يعمل.
 */
class AuditProjectSite implements ShouldQueue
{
    use Queueable;

    /** محاولتان فقط: تعذّر الوصول نتيجة مشروعة لا خطأ يُعاد حتى ينجح. */
    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        public readonly int $projectId,
        public readonly string $url,
    ) {}

    public function handle(ReadinessCollector $collector): void
    {
        $project = Project::find($this->projectId);

        if ($project === null) {
            return;
        }

        $result = $collector->collectSiteAudit($project, $this->url);

        if (! $result->reachable) {
            // يُسجَّل ولا يُرمى: الفشل هنا معلومة عن الموقع لا عطل في النظام.
            Log::info(__('تعذّر الوصول إلى موقع المشروع للتدقيق.'), [
                'project_id' => $project->id,
                'url' => $this->url,
            ]);
        }
    }
}
