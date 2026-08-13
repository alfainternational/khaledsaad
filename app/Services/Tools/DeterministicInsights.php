<?php

namespace App\Services\Tools;

use App\Models\ToolRun;
use App\Modules\Reporting\Objectives\ObjectiveCatalog;

/**
 * أرضية النتائج الحتمية: ما يستحقه صاحب المشروع حتى لو تعطّل الذكاء الاصطناعي تمامًا.
 *
 * السبب: التقرير كان يصل أحيانًا بلا توصية واحدة حين تفشل مرحلة الخلاصة،
 * فيقود العميل بإتقان إلى بابٍ مغلق. هذه الطبقة تشتق نتائج وتوصيات حقيقية
 * من درجته المحسوبة بقواعد ثابتة — من إجاباته هو، لا من نموذج خارجي.
 *
 * تتوافق مع اتجاه سيادة البيانات: الذكاء الأساسي محلي، والخارجي يحسّنه لا يصنعه.
 */
class DeterministicInsights
{
    public function __construct(private readonly ObjectiveCatalog $objectives) {}
    /**
     * يحوّل تفصيل الدرجة إلى نتائج مرتبة حسب الأثر: الأضعف عائدًا أولًا.
     *
     * @param  array{score: int, band: string, breakdown: array<int, array<string, mixed>>}  $baseline
     * @return array<int, array<string, mixed>> بنفس شكل مخرج الخلاصة، جاهز لـ ReportComposer.
     */
    public function findings(ToolRun $run, array $baseline, int $limit = 3): array
    {
        $advice = $this->adviceMap($run);

        // الأولوية = الوزن × مقدار النقص. البند مرتفع الوزن ومنخفض العائد يتصدّر.
        $ranked = collect($baseline['breakdown'])
            ->map(function (array $row) {
                $factor = (float) ($row['factor'] ?? 1);
                $weight = (float) ($row['weight'] ?? 1);

                return [...$row, 'gap' => $weight * (1 - $factor)];
            })
            ->filter(fn (array $row) => $row['gap'] > 0.01)
            ->sortByDesc('gap')
            ->take($limit)
            ->values();

        return $ranked
            ->map(fn (array $row) => $this->toFinding(
                $row,
                $advice[$row['field']] ?? null,
                $this->objectives->forField($run->toolVersion->tool->key, (string) ($row['field'] ?? '')),
            ))
            ->all();
    }

    /**
     * مراسي مرحلة التركيب: أضعف بنود الدرجة (الأدنى نقاطًا) مع نصيحتها المُنسَّقة.
     *
     * تُمرَّر إلى مرحلة الخلاصة ليبني الذكاء توصياته على مواطن الضعف الفعلية لا
     * على ترتيب حر، من نفس مصدر weak_advice الذي تقرأه الأرضية الحتمية — لا مصدر ثانٍ.
     *
     * @param  array{breakdown: array<int, array<string, mixed>>}  $baseline
     * @return array<int, array<string, mixed>>
     */
    public function anchors(ToolRun $run, array $baseline, int $limit = 3): array
    {
        $advice = $this->adviceMap($run);

        return collect($baseline['breakdown'] ?? [])
            ->sortBy('points')
            ->take($limit)
            ->map(fn (array $row) => [
                'field' => $row['field'] ?? null,
                'label' => $row['label'] ?? ($row['field'] ?? null),
                'points' => $row['points'] ?? null,
                'weight' => $row['weight'] ?? null,
                'advice' => $advice[$row['field'] ?? ''] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{title?: string, description?: string, recommendation?: string, kpi?: string}|null  $advice
     * @return array<string, mixed>
     */
    private function toFinding(array $row, ?array $advice, ?string $objectiveId): array
    {
        $label = (string) ($row['label'] ?? $row['field']);
        $factor = (float) ($row['factor'] ?? 0);

        // شدة النتيجة من مقدار النقص نفسه، لا من حكم نموذج.
        $severity = match (true) {
            $factor <= 0.25 => 'high',
            $factor <= 0.6 => 'medium',
            default => 'low',
        };

        $title = $advice['title'] ?? "«{$label}» هو أول ما يستحق انتباهك";
        $description = $advice['description']
            ?? 'هذا من أضعف النقاط عندك الآن، وهو في نفس الوقت من أكثرها تأثيرًا على نتيجتك. يعني لو رتّبته، تكسب أكبر تحسّن بأقل مجهود.';

        $recommendation = $advice['recommendation']
            ?? "خذ وقتًا هذا الأسبوع لـ«{$label}»: اكتب وضعه الحالي بصراحة، ثم اختر خطوة واحدة صغيرة تنقله للأفضل.";

        return [
            'source_field' => (string) ($row['field'] ?? ''),
            'title' => $title,
            'description' => $description,
            'category' => 'ابدأ من هنا',
            'severity' => $severity,
            // حتمية لا افتراضية: مبنية على إجاباته ودرجته لا على تخمين.
            'is_assumption' => false,
            'evidence' => "هذا الجانب وصل {$this->percent($factor)}% من الكامل، وهو من أكثر النقاط تأثيرًا في نتيجتك.",
            'confidence' => 90,
            'recommendations' => [[
                'objective_id' => $objectiveId,
                'title' => $advice['title'] ?? "اجعل «{$label}» أول خطوة",
                'description' => $recommendation,
                'impact' => $factor <= 0.4 ? 'high' : 'medium',
                'effort' => 'medium',
                'duration_days' => 7,
                'deliverable' => "ورقة عمل مكتملة تخص «{$label}»",
                'done_when' => "يمكن فحص تحسين «{$label}» من ورقة واحدة مكتملة بنعم أو لا.",
                'first_five_minutes' => "افتح مستندًا جديدًا واكتب عنوان «{$label}» والوضع المثبت حاليًا.",
                'expected_failure' => 'قد يتسع العمل إلى أكثر من قرار؛ التزم بناتج واحد قابل للفحص هذا الأسبوع.',
                'metric' => [
                    'label' => $advice['kpi'] ?? "اكتمال ناتج «{$label}» والتحقق منه",
                    'objective_id' => $objectiveId,
                ],
                'action_steps' => [
                    "استخرج من إجابتك الحالية حقيقة واحدة تخص «{$label}» واكتبها في أعلى الورقة.",
                    "حوّل هذه الحقيقة إلى قرار واحد قابل للتنفيذ ثم راجعه بنهاية الأسبوع.",
                ],
                'kpi_hint' => $advice['kpi'] ?? null,
            ]],
        ];
    }

    /**
     * نصائح مكتوبة بلغة العميل لكل بند من بنود الدرجة، تُقرأ من تعريف الأداة.
     *
     * @return array<string, array{title?: string, description?: string, recommendation?: string, kpi?: string}>
     */
    private function adviceMap(ToolRun $run): array
    {
        $rules = $run->toolVersion->scoring_rules['rules'] ?? [];
        $map = [];

        foreach ($rules as $rule) {
            if (! empty($rule['weak_advice'])) {
                $map[$rule['field']] = $rule['weak_advice'];
            }
        }

        return $map;
    }

    private function percent(float $factor): int
    {
        return (int) round(max(0, min(1, $factor)) * 100);
    }
}
