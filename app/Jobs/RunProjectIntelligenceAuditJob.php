<?php

namespace App\Jobs;

use App\Application\Execution\GenerateRecommendationsFromAuditAction;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Notification\Services\PushGateway;
use App\Support\Intelligence\MarketingIntelligenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunProjectIntelligenceAuditJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $auditRunId,
    ) {}

    public function handle(MarketingIntelligenceService $service): void
    {
        $auditRun = AuditRun::query()->find($this->auditRunId);
        if (! $auditRun) {
            return;
        }

        $auditRun = $service->execute($auditRun);

        // Phase ج: a completed audit immediately yields prioritised recommendations,
        // so the diagnosis is never just a report — it is ready to execute.
        if ($auditRun->status === 'completed') {
            $project = $auditRun->project()->first();
            if ($project) {
                app(GenerateRecommendationsFromAuditAction::class)->handle($project, $auditRun);

                // إشعار push لمالك الحساب (no-op إن لم تُضبط FCM).
                $creator = $project->workspace?->account?->owner;
                if ($creator) {
                    app(PushGateway::class)->sendToUser(
                        $creator,
                        'اكتمل تحليل مشروعك',
                        'تحليل «'.$project->name.'» جاهز مع توصيات عملية.',
                        [
                            'type' => 'audit_completed',
                            'project_public_id' => (string) $project->public_id,
                        ],
                    );
                }
            }
        }
    }
}
