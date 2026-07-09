<?php

namespace App\Domain\AI\Kernel\Cognition;

use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;

/**
 * محرّك التنبؤ — يتوقّع المسار والعائق القادم.
 *
 * يغلق حلقة التعلّم: يقرأ الأنماط التي تعلّمها النظام (KnowledgeStore عبر ai:learn)
 * من الاستخدام الفعلي، فتتحسّن تنبؤاته مع الوقت دون أي إعادة تدريب أو موارد.
 */
class PredictionEngine
{
    public function __construct(private readonly KnowledgeStore $knowledge) {}

    /**
     * @param  array<string, mixed>  $reasoning  ناتج ReasoningEngine::reason
     * @return array<string, mixed>
     */
    public function predict(Workspace $workspace, ?Project $project, array $reasoning): array
    {
        $completionRatio = (float) ($reasoning['coverage_ratio'] ?? 0.0);
        $momentum = $this->momentum($workspace, $project);
        $nextTool = $this->nextLikelyTool($reasoning);

        return [
            'projected_completion_pct' => (int) round($completionRatio * 100),
            'momentum' => $momentum,
            'next_likely_focus' => $nextTool,
            'predicted_blocker' => $this->predictedBlocker($nextTool, $reasoning),
            'forecast' => $this->forecast($completionRatio, $momentum),
        ];
    }

    /**
     * زخم العمل: نشاط آخر 14 يوماً مقابل ما قبلها.
     */
    private function momentum(Workspace $workspace, ?Project $project): string
    {
        $base = ToolRun::query()
            ->where('workspace_id', $workspace->getKey())
            ->when($project !== null, fn ($q) => $q->where('project_id', $project->getKey()));

        $recent = (clone $base)->where('created_at', '>=', now()->subDays(14))->count();
        $prior = (clone $base)->whereBetween('created_at', [now()->subDays(28), now()->subDays(14)])->count();

        if ($recent === 0 && $prior === 0) {
            return 'لم يبدأ';
        }
        if ($recent > $prior) {
            return 'متصاعد';
        }
        if ($recent === 0) {
            return 'متوقّف';
        }

        return $recent < $prior ? 'متباطئ' : 'ثابت';
    }

    /**
     * @param  array<string, mixed>  $reasoning
     */
    private function nextLikelyTool(array $reasoning): ?string
    {
        $stages = $reasoning['stages'] ?? [];
        foreach ($stages as $stage) {
            $missing = $stage['missing'] ?? [];
            if (is_array($missing) && $missing !== []) {
                return (string) $missing[0];
            }
        }

        return null;
    }

    /**
     * العائق المتوقّع — يُثرى بنمط التوقّف العالمي المتعلَّم إن توفّر.
     *
     * @param  array<string, mixed>  $reasoning
     */
    private function predictedBlocker(?string $nextTool, array $reasoning): ?string
    {
        $risks = $reasoning['risks'] ?? [];
        if (is_array($risks) && $risks !== []) {
            return (string) $risks[0];
        }

        $learned = $this->knowledge->recall('patterns.global');
        $dropOff = $learned['data']['common_drop_off_tool'] ?? null;
        if (is_string($dropOff) && $dropOff === $nextTool) {
            return 'هذه الأداة هي الأكثر توقّفاً عندها لدى المستخدمين — خصّص لها وقتاً كافياً ولا تتجاوزها سريعاً.';
        }

        return null;
    }

    private function forecast(float $completionRatio, string $momentum): string
    {
        if ($completionRatio >= 0.9) {
            return 'المشروع شبه مكتمل التأسيس — التركيز القادم على القياس والتوسّع.';
        }
        if ($momentum === 'متصاعد') {
            return 'بهذا الإيقاع، أنت في طريقك لإغلاق المرحلة الحالية قريباً — استمر.';
        }
        if ($momentum === 'متوقّف' || $momentum === 'متباطئ') {
            return 'الإيقاع تباطأ — إنجاز أداة واحدة هذا الأسبوع يعيد الزخم ويمنع فقدان السياق.';
        }

        return 'ابدأ بأداة واحدة واضحة لتأسيس زخم قابل للقياس.';
    }
}
