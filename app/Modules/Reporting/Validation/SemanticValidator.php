<?php

namespace App\Modules\Reporting\Validation;

use Illuminate\Support\Arr;

class SemanticValidator
{
    public const BOILERPLATE_STEPS = [
        'اكتب في سطر واحد الوضع الحالي عندك في هذه النقطة، بصراحة وبلا تجميل.',
        'حدّد أصغر تغيير ممكن ينقلك خطوة للأمام هذا الأسبوع.',
        'نفّذه على حالة واحدة فقط (عميل واحد، صفحة واحدة، قناة واحدة).',
        'سجّل ما حصل بعد أسبوع: ماذا تغيّر ومَن لاحظ.',
    ];

    private const SEVERITY = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    public function __construct(private readonly ArabicJaccard $jaccard) {}

    /** @param array<string, mixed> $report */
    public function validate(array $report): ValidationReport
    {
        $violations = [];
        $recommendations = [];

        foreach (array_values(Arr::wrap($report['findings'] ?? [])) as $index => $finding) {
            $path = "findings.{$index}";
            $recommendation = is_array($finding['recommendation'] ?? null) ? $finding['recommendation'] : null;

            if ($recommendation !== null) {
                $recommendations[] = [$index, $recommendation];
                if (! (bool) ($recommendation['degraded'] ?? false)) {
                    $this->validateRecommendation($recommendation, $path.'.recommendation', $report, $violations);
                }
            }

            $answerRef = trim((string) data_get($finding, 'evidence.answer_ref', ''));
            $quote = trim((string) data_get($finding, 'evidence.quote', ''));
            if ($answerRef === '' && ! (bool) ($finding['is_assumption'] ?? false)) {
                $this->add($violations, 'R09', 'block', $path.'.evidence.answer_ref', 'النتيجة غير مرتبطة بإجابة مصدرية.', 'اربط النتيجة بمعرّف الإجابة التي تثبتها.');
            }
            if ($answerRef === '' && $quote === '' && ! (bool) ($finding['is_assumption'] ?? false)) {
                $this->add($violations, 'R10', 'warn', $path.'.is_assumption', 'الاستنتاج غير المدعوم غير موسوم كافتراض.', 'وسم النتيجة كافتراض أو أضف الدليل.');
            }
            if ($this->hasBrokenArabic((string) ($finding['title'] ?? '').' '.(string) ($finding['description'] ?? ''))) {
                $this->add($violations, 'R15', 'warn', $path, 'يوجد نص لاتيني ملتصق داخل جملة عربية.', 'اعزل المصطلح اللاتيني بمسافات أو ترجمه.');
            }
        }

        $this->validateDuplicateSteps($recommendations, $violations);
        $this->validateScore($report, $violations);
        $this->validateSeverityOrder($report, $violations);

        if (in_array($report['provenance'] ?? 'automated', ['signed', 'hybrid'], true)
            && Arr::wrap($report['human_traces'] ?? []) === []) {
            $this->add($violations, 'R13', 'block', 'human_traces', 'المخرج الموقّع أو المختلط بلا أثر بشري.', 'أضف ملاحظة أو إعادة ترتيب أو تجاوزًا مسجّلًا.');
        }

        return new ValidationReport($violations);
    }

    /** @param array<string, mixed> $recommendation @param array<int, ValidationViolation> $violations */
    private function validateRecommendation(array $recommendation, string $path, array $report, array &$violations): void
    {
        $objective = trim((string) ($recommendation['objective_id'] ?? ''));
        $templateObjective = trim((string) data_get($recommendation, 'template.objective_id', ''));
        $metricObjective = trim((string) data_get($recommendation, 'metric.objective_id', ''));

        if ($objective === '' || $templateObjective !== $objective) {
            $this->add($violations, 'R01', 'block', $path.'.template.objective_id', 'هدف القالب لا يطابق هدف التوصية.', 'اختر القالب بواسطة الهدف نفسه فقط.');
        }
        if ($metricObjective !== $objective) {
            $this->add($violations, 'R04', 'block', $path.'.metric.objective_id', 'المؤشر لا يقيس هدف التوصية.', 'اربط المؤشر بالهدف نفسه.');
        }

        $steps = array_values(array_map('strval', Arr::wrap($recommendation['steps'] ?? $recommendation['action_steps'] ?? [])));
        if ($this->jaccard->similarity($steps, self::BOILERPLATE_STEPS) >= .98) {
            $this->add($violations, 'R03', 'block', $path.'.steps', 'قائمة الخطوات العامة المحظورة مستخدمة كتوصية.', 'انقلها إلى fallback_coaching واكتب خطوات تخص الهدف.');
        }

        $templateText = $this->flatten(data_get($recommendation, 'template.blocks', []));
        if ($this->jaccard->similarity($steps, $templateText) > .60) {
            $this->add($violations, 'R05', 'block', $path.'.template', 'القالب نسخة من الخطوات وليس أداة عمل.', 'استخدم قالبًا له حقول ومخرجات تخص الهدف.');
        }

        $allText = $this->flatten($recommendation);
        if (preg_match('/اطلب\s+(?:تطوير|إكمال)|لاحق[ًاا]|سنرسل|سوف\s+(?:نرسل|نجهز)/u', $allText)) {
            $this->add($violations, 'R06', 'block', $path, 'النص يحيل المستخدم إلى مخرج لاحق غير مرفق.', 'أرفق المخرج الآن أو خفّض التوصية.');
        }

        preg_match_all('/\[([^\]]+)\]/u', $templateText, $matches);
        $known = array_map('strval', Arr::wrap($report['known_placeholders'] ?? []));
        foreach ($matches[1] ?? [] as $placeholder) {
            if (! in_array($placeholder, $known, true)) {
                $this->add($violations, 'R07', 'warn', $path.'.template', "الحقل [{$placeholder}] غير مربوط بسياق معروف.", 'أضف binding معروفًا أو احذف الحقل.');
            }
        }

        foreach (['deliverable', 'done_when', 'first_five_minutes', 'expected_failure', 'template'] as $field) {
            if (blank($recommendation[$field] ?? null)) {
                $this->add($violations, 'R08', 'block', $path.'.'.$field, 'حقل إلزامي في عقد التوصية فارغ.', 'أكمل الحقل أو خفّض التوصية.');
            }
        }

        if (! in_array($recommendation['impact'] ?? null, ['high', 'medium', 'low'], true)
            || ! in_array($recommendation['effort'] ?? null, ['high', 'medium', 'low'], true)
            || (int) ($recommendation['duration_days'] ?? 0) < 1) {
            $this->add($violations, 'R14', 'block', $path, 'الأثر أو الجهد أو المدة غير مكتمل.', 'حدد الأثر والجهد ومدة موجبة بالأيام.');
        }

        if ($this->hasBrokenArabic($allText)) {
            $this->add($violations, 'R15', 'warn', $path, 'يوجد نص لاتيني ملتصق داخل جملة عربية.', 'اعزل المصطلح اللاتيني بمسافات أو ترجمه.');
        }
    }

    /** @param array<int, array{0:int,1:array<string,mixed>}> $items @param array<int, ValidationViolation> $violations */
    private function validateDuplicateSteps(array $items, array &$violations): void
    {
        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                $left = $items[$i][1]['steps'] ?? $items[$i][1]['action_steps'] ?? [];
                $right = $items[$j][1]['steps'] ?? $items[$j][1]['action_steps'] ?? [];
                if ($this->jaccard->similarity(Arr::wrap($left), Arr::wrap($right)) > .70) {
                    $this->add($violations, 'R02', 'block', "findings.{$items[$j][0]}.recommendation.steps", 'الخطوات مكررة بين توصيتين.', 'اكتب خطوات مستقلة تخدم هدف كل توصية.');
                }
            }
        }
    }

    /** @param array<string, mixed> $report @param array<int, ValidationViolation> $violations */
    private function validateScore(array $report, array &$violations): void
    {
        $raw = array_sum(array_map(fn ($item): float => (float) ($item['points'] ?? 0), Arr::wrap($report['scoring'] ?? [])));
        $max = (float) data_get($report, 'score.max', 0);
        $shown = (float) data_get($report, 'score.value', 0);
        $calculated = $max > 0 ? ($raw / $max) * 100 : 0;
        if ($max <= 0 || abs($shown - $calculated) > .5 || abs((float) data_get($report, 'score.raw', $raw) - $raw) > .5) {
            $this->add($violations, 'R11', 'block', 'score', 'الدرجة المعروضة لا تطابق مجموع عناصر الحساب.', 'أعد حساب الدرجة من raw/max والعناصر المحفوظة.');
        }
    }

    /** @param array<string, mixed> $report @param array<int, ValidationViolation> $violations */
    private function validateSeverityOrder(array $report, array &$violations): void
    {
        $last = PHP_INT_MAX;
        foreach (array_values(Arr::wrap($report['findings'] ?? [])) as $index => $finding) {
            $rank = self::SEVERITY[$finding['severity'] ?? 'low'] ?? 0;
            if ($rank > $last) {
                $this->add($violations, 'R12', 'warn', "findings.{$index}.severity", 'النتائج غير مرتبة تنازليًا حسب الخطورة.', 'رتب critical ثم high ثم medium ثم low.');
                break;
            }
            $last = $rank;
        }
    }

    private function hasBrokenArabic(string $text): bool
    {
        return (bool) preg_match('/(?:\p{Arabic}[A-Za-z]|[A-Za-z]\p{Arabic})/u', $text);
    }

    private function flatten(mixed $value): string
    {
        if (is_array($value)) {
            return implode(' ', array_map($this->flatten(...), $value));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /** @param array<int, ValidationViolation> $violations */
    private function add(array &$violations, string $code, string $severity, string $path, string $message, string $action): void
    {
        $violations[] = new ValidationViolation($code, $severity, $path, $message, $action);
    }
}
