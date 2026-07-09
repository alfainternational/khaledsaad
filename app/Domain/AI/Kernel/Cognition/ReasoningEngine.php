<?php

namespace App\Domain\AI\Kernel\Cognition;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;

/**
 * محرّك الاستدلال والاستنباط — يشتقّ حقائق جديدة لم تُكتب صراحةً.
 *
 * المنهج (deterministic، بلا LLM):
 *  - الاستقراء (induction): يقيس تغطية الأدوات الفعلية من tool_runs.
 *  - الاستنباط (deduction): يطبّق متطلبات المراحل ليحدّد الجاهز والناقص.
 *  - الاستدلال (inference): يكشف مخاطر منطقية (أداة نُفّذت بلا مرجعيتها).
 */
class ReasoningEngine
{
    /**
     * خريطة المراحل الخمس وأدواتها (المرجع: CLAUDE.md).
     *
     * @var array<int, array{title: string, tools: array<int, string>}>
     */
    private const STAGES = [
        1 => ['title' => 'اكتشف مشروعك', 'tools' => ['diagnosis', 'idea-clarity', 'swot-analysis', 'goal-definition', 'problem-definition']],
        2 => ['title' => 'ابنِ أساسك التسويقي', 'tools' => ['tagline-builder', 'ideal-customer', 'positioning', 'market-analysis', 'competitor-analysis']],
        3 => ['title' => 'ابنِ عرضك', 'tools' => ['offer-builder', 'pricing-strategy', 'value-ladder', 'package-builder', 'promise-builder']],
        4 => ['title' => 'اجذب وحوّل', 'tools' => ['funnel-builder', 'customer-journey', 'marketing-plan', 'content-plan', 'campaign-builder', 'follow-up-sequence']],
        5 => ['title' => 'قِس ووسّع', 'tools' => ['kpi-tracker', 'execution-plan', 'performance-review', 'smart-recommendations', 'growth-priorities']],
    ];

    /**
     * علاقات منطقية: الأداة (المفتاح) تفترض وجود مرجعيتها (القيمة) أولاً.
     *
     * @var array<string, array<int, string>>
     */
    private const PREREQUISITES = [
        'offer-builder' => ['ideal-customer', 'positioning'],
        'pricing-strategy' => ['offer-builder', 'competitor-analysis'],
        'value-ladder' => ['offer-builder', 'pricing-strategy'],
        'marketing-plan' => ['ideal-customer', 'offer-builder'],
        'content-plan' => ['ideal-customer'],
        'campaign-builder' => ['marketing-plan'],
        'funnel-builder' => ['offer-builder'],
        'follow-up-sequence' => ['ideal-customer'],
        'kpi-tracker' => ['goal-definition'],
    ];

    /**
     * @return array<string, mixed>
     */
    public function reason(Workspace $workspace, ?Project $project = null): array
    {
        $completed = $this->completedTools($workspace, $project);

        $stages = [];
        $firstIncompleteStage = null;
        foreach (self::STAGES as $num => $stage) {
            $done = array_values(array_intersect($stage['tools'], $completed));
            $ratio = count($stage['tools']) > 0 ? count($done) / count($stage['tools']) : 0.0;
            $stages[$num] = [
                'title' => $stage['title'],
                'done' => count($done),
                'total' => count($stage['tools']),
                'ratio' => round($ratio, 2),
                'missing' => array_values(array_diff($stage['tools'], $completed)),
            ];
            if ($firstIncompleteStage === null && $ratio < 1.0) {
                $firstIncompleteStage = $num;
            }
        }

        return [
            'coverage' => count($completed),
            'coverage_ratio' => $this->coverageRatio($completed),
            'completed_tools' => $completed,
            'stages' => $stages,
            'current_stage' => $firstIncompleteStage ?? 5,
            'deductions' => $this->deduce($completed, $firstIncompleteStage, $stages),
            'risks' => $this->infer($completed),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function completedTools(Workspace $workspace, ?Project $project): array
    {
        return ToolRun::query()
            ->where('workspace_id', $workspace->getKey())
            ->when($project !== null, fn ($q) => $q->where('project_id', $project->getKey()))
            ->distinct()
            ->pluck('tool_code')
            ->filter()
            ->map(fn ($c): string => (string) $c)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $completed
     */
    private function coverageRatio(array $completed): float
    {
        $total = 0;
        foreach (self::STAGES as $stage) {
            $total += count($stage['tools']);
        }

        return $total > 0 ? round(count(array_unique($completed)) / $total, 2) : 0.0;
    }

    /**
     * الاستنباط: ماذا يمكن استنتاجه عن جاهزية المشروع؟
     *
     * @param  array<int, string>  $completed
     * @param  array<int, array<string, mixed>>  $stages
     * @return array<int, string>
     */
    private function deduce(array $completed, ?int $firstIncompleteStage, array $stages): array
    {
        $out = [];

        if ($completed === []) {
            return ['لم تُنفَّذ أي أداة بعد — ابدأ بالتشخيص لتأسيس قاعدة معرفية للمشروع.'];
        }

        if ($firstIncompleteStage !== null && isset($stages[$firstIncompleteStage])) {
            $stage = $stages[$firstIncompleteStage];
            $missingCount = count($stage['missing']);
            $out[] = sprintf(
                'أنت في مرحلة «%s» (%d من %d). يتبقّى %d أداة لإغلاق هذه المرحلة قبل الانتقال.',
                $stage['title'], $stage['done'], $stage['total'], $missingCount,
            );
        }

        if (in_array('ideal-customer', $completed, true) && in_array('positioning', $completed, true) && ! in_array('offer-builder', $completed, true)) {
            $out[] = 'حدّدت العميل المثالي والتموضع — أنت جاهز الآن لبناء العرض بثقة.';
        }

        if (in_array('offer-builder', $completed, true) && ! in_array('pricing-strategy', $completed, true)) {
            $out[] = 'العرض جاهز لكن بلا تسعير مبني على القيمة — التسعير هو خطوتك الأعلى أثراً.';
        }

        return $out;
    }

    /**
     * الاستدلال: مخاطر منطقية من تنفيذ أداة بلا مرجعيتها.
     *
     * @param  array<int, string>  $completed
     * @return array<int, string>
     */
    private function infer(array $completed): array
    {
        $labels = [
            'ideal-customer' => 'العميل المثالي',
            'positioning' => 'التموضع',
            'offer-builder' => 'العرض',
            'competitor-analysis' => 'تحليل المنافسين',
            'pricing-strategy' => 'التسعير',
            'marketing-plan' => 'الخطة التسويقية',
            'goal-definition' => 'تحديد الهدف',
        ];

        $risks = [];
        foreach (self::PREREQUISITES as $tool => $needs) {
            if (! in_array($tool, $completed, true)) {
                continue;
            }
            $missing = array_values(array_diff($needs, $completed));
            if ($missing !== []) {
                $missingAr = implode('، ', array_map(fn (string $m): string => $labels[$m] ?? $m, $missing));
                $risks[] = sprintf('«%s» نُفّذت دون: %s — قد تكون نتيجتها على أساس ناقص.', $labels[$tool] ?? $tool, $missingAr);
            }
        }

        return $risks;
    }
}
