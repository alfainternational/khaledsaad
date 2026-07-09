<?php

namespace App\Application\Intelligence;

use App\Domain\AI\Kernel\Cognition\PredictionEngine;
use App\Domain\AI\Kernel\Cognition\ReasoningEngine;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Support\Dashboard\NextStepRecommendationService;

/**
 * مُجمِّع الذكاء (Compile-Ahead): يحسب الرؤية المعرفية الكاملة مرة واحدة وقت
 * الكتابة (بعد كل تشغيل أداة) ويخزّنها كـ artifact ثابت في workspace_data.
 *
 * فلسفة DeepSeek مُترجَمة: التفكير وقت الفراغ، والعرض = قراءة ملف (سرعة HTML،
 * صفر حساب وقت الطلب، صفر عملية خلفية دائمة).
 */
class CompileWorkspaceIntelligenceAction
{
    public const SNAPSHOT_KEY = 'ai.intelligence.snapshot';

    public function __construct(
        private readonly ReasoningEngine $reasoning,
        private readonly PredictionEngine $prediction,
        private readonly NextStepRecommendationService $nextStep,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Workspace $workspace, ?Project $project = null): array
    {
        $reasoning = $this->reasoning->reason($workspace, $project);
        $prediction = $this->prediction->predict($workspace, $project, $reasoning);
        $next = $this->nextStep->forWorkspace($workspace, $project);

        $bullets = [];
        foreach ((array) ($reasoning['deductions'] ?? []) as $d) {
            $bullets[] = (string) $d;
        }
        $blocker = (string) ($prediction['predicted_blocker'] ?? '');
        if ($blocker !== '') {
            $bullets[] = 'العائق المتوقّع: '.$blocker;
        }
        foreach (array_slice((array) ($reasoning['risks'] ?? []), 0, 2) as $r) {
            if ((string) $r !== $blocker) {
                $bullets[] = 'تنبيه منطقي: '.$r;
            }
        }

        $actions = [];
        if (! empty($next['action_label'])) {
            $actions[] = array_filter([
                'label' => (string) $next['action_label'],
                'route' => isset($next['action_route']) ? (string) $next['action_route'] : null,
            ]);
        }

        $snapshot = [
            'headline' => (string) ($next['title'] ?? 'خطوتك التالية'),
            'body' => (string) ($next['summary'] ?? ($prediction['forecast'] ?? '')),
            'insight_headline' => sprintf(
                'إنجاز %d%% · الزخم: %s',
                (int) ($prediction['projected_completion_pct'] ?? 0),
                (string) ($prediction['momentum'] ?? 'غير محدد'),
            ),
            'bullets' => array_slice(array_values(array_filter($bullets, fn (string $s): bool => trim($s) !== '')), 0, 5),
            'actions' => $actions,
            'reasoning' => $reasoning,
            'prediction' => $prediction,
            'source' => 'local',
            'compiled_at' => now()->toIso8601String(),
        ];

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->getKey(),
                'project_id' => $project?->getKey(),
                'key' => self::SNAPSHOT_KEY,
            ],
            ['value_json' => $snapshot],
        );

        return $snapshot;
    }
}
