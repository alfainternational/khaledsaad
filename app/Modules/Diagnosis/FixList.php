<?php

namespace App\Modules\Diagnosis;

use App\Models\Project;
use App\Modules\AiReadiness\SiteAuditResult;

/**
 * قائمة الإصلاح: كل فجوة مرتّبة على محورَي الأثر × الجهد.
 *
 * المخرج الذي لا يقود إلى قرار يُحذف (§٦). «درجتك ٤١» ليست قرارًا، و«أضف
 * JSON-LD من نوع Organization هذا الأسبوع» قرار.
 *
 * الترتيب حتمي: الأثر من وزن المدخل داخل محوره، والجهد من طبيعة الإصلاح لا
 * من تقدير. البند الذي يُصلح في ساعة ويرفع الدرجة كثيرًا يتصدّر دائمًا.
 */
class FixList
{
    /**
     * تقدير الجهد لكل بند، بمعرفة من يعرف الشغل لا بتخمين نموذج.
     *
     * ثلاث درجات لا خمس: صاحب النشاط يحتاج أن يعرف «هل أفعلها اليوم أم
     * أجدولها»، والتدرّج الأدق يوهم بدقة غير موجودة.
     */
    private const EFFORT = [
        'schema_organization' => 'low',
        'llms_txt' => 'low',
        'ai_bots_allowed' => 'low',
        'prices_machine_readable' => 'medium',
        'policy_pages' => 'medium',
        'arabic_page_structure' => 'medium',
        'schema_products' => 'high',
        'ai_bot_visits_30d' => 'high',
    ];

    private const EFFORT_LABEL = ['low' => 'سريع', 'medium' => 'متوسط', 'high' => 'يحتاج عملًا'];

    private const EFFORT_RANK = ['low' => 0, 'medium' => 1, 'high' => 2];

    public function __construct(
        private readonly AxisScorer $scorer,
        private readonly AxisRegistry $registry,
    ) {}

    /**
     * @param  array<int, Axis>|null  $axes  المحاور المشمولة. null = المفعّلة كلها.
     * @return array<int, array<string, mixed>>
     */
    public function build(Project $project, ?array $axes = null, ?SiteAuditResult $audit = null): array
    {
        $scores = $axes === null
            ? $this->scorer->scoreAll($project)
            : array_map(fn (Axis $axis) => $this->scorer->score($project, $axis), $axes);

        $fixes = [];
        $repairs = $audit === null ? [] : $this->repairsFrom($audit);

        foreach ($scores as $score) {
            foreach ($this->registry->inputsFor($score->axis) as $input) {
                if (! in_array($input['label'], $score->gaps, true)) {
                    continue;
                }

                $effort = self::EFFORT[$input['key']] ?? 'medium';
                $impact = (float) ($input['weight'] ?? 1) * $score->axis->weight();

                $fixes[] = [
                    'key' => $input['key'],
                    'axis' => $score->axis->value,
                    'axis_label' => $score->axis->label(),
                    'title' => $input['label'],
                    'impact' => round($impact, 2),
                    'effort' => $effort,
                    'effort_label' => self::EFFORT_LABEL[$effort],

                    /*
                     * وسم الفرضية ينتقل مع البند: فجوة في محور استنتاجي هي
                     * رأي منهجي لا عيب مرصود، وعرضها بلا تمييز يجعل المستخدم
                     * يصلح ما لم نتأكد أنه مكسور (§٤.١).
                     */
                    'evidence_level' => $score->evidenceLevel->value,
                    'is_assumption' => $score->evidenceLevel->needsAssumptionBadge(),
                    'why' => $repairs[$input['key']]['why'] ?? null,
                    'fix' => $repairs[$input['key']]['fix'] ?? null,
                ];
            }
        }

        usort($fixes, function (array $a, array $b): int {
            // الأثر أولًا، فالأقل جهدًا عند التساوي: هذا ما يجعل القائمة
            // قابلة للبدء بها اليوم لا خطة ربع سنوية.
            return [$b['impact'], self::EFFORT_RANK[$a['effort']]]
                <=> [$a['impact'], self::EFFORT_RANK[$b['effort']]];
        });

        return $fixes;
    }

    /**
     * أعلى ثلاث فجوات بالاسم دون الحل — مخرج المستوى ٠.
     *
     * الفجوة المعروضة بلا حلّها تخلق فجوة معرفية ولا تقفلها. أي زيادة على
     * هذا تقتل التحويل (§٦)، ولذلك يُجرَّد البند هنا من `why` و`fix` صراحةً
     * لا بإخفائهما في الواجهة.
     *
     * @return array<int, array<string, mixed>>
     */
    public function teaser(Project $project, ?array $axes = null): array
    {
        return array_map(
            fn (array $fix) => [
                'title' => $fix['title'],
                'axis_label' => $fix['axis_label'],
                'is_assumption' => $fix['is_assumption'],
            ],
            array_slice($this->build($project, $axes), 0, 3),
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function repairsFrom(SiteAuditResult $audit): array
    {
        $map = [];

        foreach ($audit->checklist() as $item) {
            $map[$item['key']] = ['why' => $item['why'], 'fix' => $item['fix']];
        }

        return $map;
    }
}
