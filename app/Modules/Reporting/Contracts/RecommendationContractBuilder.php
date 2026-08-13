<?php

namespace App\Modules\Reporting\Contracts;

use App\Models\Objective;
use App\Modules\Reporting\Objectives\ObjectiveCatalog;
use App\Modules\Reporting\Templates\TemplateResolver;

class RecommendationContractBuilder
{
    public function __construct(
        private readonly ObjectiveCatalog $catalog,
        private readonly TemplateResolver $templates,
    ) {}

    /**
     * إرشاد أخير حين تغيب خطوات النموذج.
     *
     * كان ثابتًا عربيًّا صرفًا، فيصل قارئ التقرير الفرنسي إلى آخر ما يملكه
     * النظام له مكتوبًا بلغة لا يقرؤها. والثابت لا يقبل `__()`، فصار تابعًا.
     *
     * @return array<int, string>
     */
    private function genericCoaching(string $locale): array
    {
        return [
            __('اكتب في سطر واحد الوضع الحالي عندك في هذه النقطة، بصراحة وبلا تجميل.', [], $locale),
            __('حدّد أصغر تغيير ممكن ينقلك خطوة للأمام هذا الأسبوع.', [], $locale),
            __('نفّذه على حالة واحدة فقط (عميل واحد، صفحة واحدة، قناة واحدة).', [], $locale),
            __('سجّل ما حصل بعد أسبوع: ماذا تغيّر ومَن لاحظ.', [], $locale),
        ];
    }

    /** @param array<string, mixed> $payload @param array<string, mixed> $context */
    public function build(array $payload, string $toolKey, string $fieldKey, array $context): RecommendationCandidate
    {
        $allowed = $this->catalog->allowedForTool($toolKey);
        $requested = $this->text($payload['objective_id'] ?? null);
        $objectiveId = $requested ?: ($this->catalog->forField($toolKey, $fieldKey) ?? '');
        $reasons = [];

        if ($requested !== '' && ! in_array($requested, $allowed, true)) {
            $reasons[] = 'objective_not_allowed';
        }

        $objective = Objective::query()->where('slug', $objectiveId)->first();
        if ($objective === null) {
            $reasons[] = 'missing_objective';
        }

        $metric = is_array($payload['metric'] ?? null) ? $payload['metric'] : [];
        $metricObjectiveId = $this->text($metric['objective_id'] ?? null) ?: $objectiveId;
        $metricObjective = Objective::query()->where('slug', $metricObjectiveId)->first();
        /*
         * لغة التقرير تسافر داخل السياق لا تُقرأ من `app()->getLocale()`:
         * تركيب التقرير يجري في عامل الطابور، ولغة العامل ليست لغة صاحب
         * التقرير. القراءة من الحالة العامة هنا كانت تعطي كل التقارير لغة
         * الخادم مهما كانت لغة أصحابها.
         */
        $locale = is_string($context['locale'] ?? null) && $context['locale'] !== ''
            ? $context['locale']
            : app()->getLocale();

        $template = $objectiveId !== '' ? $this->templates->forObjective($objectiveId, $context, $locale) : null;

        if ($template === null) {
            $reasons[] = 'missing_template';
        }

        $values = [
            'deliverable' => $this->text($payload['deliverable'] ?? null),
            'done_when' => $this->text($payload['done_when'] ?? null),
            'first_five_minutes' => $this->text($payload['first_five_minutes'] ?? null),
            'expected_failure' => $this->text($payload['expected_failure'] ?? null),
        ];

        if (in_array('', $values, true)) {
            $reasons[] = 'missing_contract_field';
        }

        $steps = $this->specificSteps($payload['action_steps'] ?? null, $locale);
        if ($steps === []) {
            $reasons[] = 'missing_action_steps';
        }

        $duration = max(0, (int) ($payload['duration_days'] ?? 0));
        if ($duration === 0 || ! in_array($payload['impact'] ?? null, ['high', 'medium', 'low'], true)
            || ! in_array($payload['effort'] ?? null, ['high', 'medium', 'low'], true)) {
            $reasons[] = 'effort_impact_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return new RecommendationCandidate(
            objectiveId: $objectiveId,
            objectiveDatabaseId: $objective?->id,
            metricObjectiveId: $metricObjectiveId,
            metricObjectiveDatabaseId: $metricObjective?->id,
            title: $this->text($payload['title'] ?? null),
            description: $this->text($payload['description'] ?? null),
            deliverable: $values['deliverable'],
            doneWhen: $values['done_when'],
            firstFiveMinutes: $values['first_five_minutes'],
            expectedFailure: $values['expected_failure'],
            durationDays: $duration,
            impact: $this->text($payload['impact'] ?? null),
            effort: $this->text($payload['effort'] ?? null),
            metricLabel: $this->text($metric['label'] ?? ($payload['kpi_hint'] ?? null)),
            actionSteps: $steps,
            template: $template?->toArray(),
            degraded: $reasons !== [],
            degradeReasons: $reasons,
            fallbackCoaching: $steps === [] ? $this->genericCoaching($locale) : [],
            source: $payload,
        );
    }

    private function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * خطوات من النموذج تستحق أن تُعرض إجراءً.
     *
     * الرفض حين تطابق الإرشاد العام: أحيانًا يعيد النموذج نصّ الأرضية نفسه
     * بدل أن يكتب خطوات، فيصل إلى صاحب النشاط كأنه تحليل. والمقارنة تجري مع
     * لغة التشغيل **ولغة المصدر** معًا: البرومبتات وأمثلتها عربية، فقد يعيد
     * النموذج صياغتها العربية داخل تشغيل فرنسي.
     *
     * @return array<int, string>
     */
    private function specificSteps(mixed $raw, string $locale): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $steps = array_values(array_unique(array_filter(array_map(
            fn (mixed $step): string => $this->text($step),
            $raw,
        ), fn (string $step): bool => mb_strlen($step) >= 15)));

        $canned = [
            $this->genericCoaching($locale),
            $this->genericCoaching((string) config('locales.source', 'ar')),
        ];

        if (count($steps) < 2 || in_array($steps, $canned, true)) {
            return [];
        }

        return array_slice($steps, 0, 6);
    }
}
