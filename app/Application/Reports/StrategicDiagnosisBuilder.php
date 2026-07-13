<?php

namespace App\Application\Reports;

use App\Domain\AI\Semantic\ArabicNormalizer;
use App\Domain\AI\Semantic\ConceptLexicon;
use App\Domain\AI\Semantic\LexicalSemanticMatcher;
use App\Domain\AI\Semantic\SemanticMatcher;
use App\Domain\Tool\Models\ToolRun;
use Illuminate\Support\Collection;

/**
 * محرّك التشخيص الاستراتيجي المتقاطع — قلب التقرير.
 *
 * يقرأ إجابات المستخدم عبر *كل* الأدوات (لا الملخّصات)، ويبحث عن الفجوات
 * والتناقضات بين ما يعرفه العميل وما يفعله فعلاً، فيولّد لكل مشكلة ثلاثيةً
 * إلزامية: (المشكلة ← السبب الفعلي المتقاطع ← الحل الواقعي) مع الأثر المتوقّع
 * والدليل من مدخلاته. محلي بالكامل، حتمي — لا اختلاق ولا عموميات.
 *
 * القاعدة: كل سبب وحل يعود لمدخل حقيقي (evidence). ما لا دليل له لا يُذكر.
 */
class StrategicDiagnosisBuilder
{
    /** عبارات عامة تكشف إجابة بلا مضمون. */
    private const FILLER = [
        'جودة عالية', 'حلول مبتكرة', 'الأفضل', 'خدمة متميزة', 'أسعار منافسة',
        'جميع العملاء', 'الجميع', 'كل الناس', 'متميز', 'رائد', 'احترافية عالية',
    ];

    private readonly SemanticMatcher $matcher;

    /**
     * الفهم الدلالي يُضاف فوق المطابقة المعجمية (لا يستبدلها) — توسّع الاستدعاء
     * دون تراجع. اختياري بقيمة افتراضية ليبقى الباني قابلاً للإنشاء بـ new.
     */
    public function __construct(?SemanticMatcher $matcher = null)
    {
        // نفضّل ربط الحاوية (قد يكون محرّك تضمينات) ونسقط للمعجمي خارجها.
        try {
            $this->matcher = $matcher ?? app(SemanticMatcher::class);
        } catch (\Throwable) {
            $this->matcher = new LexicalSemanticMatcher(new ArabicNormalizer, new ConceptLexicon);
        }
    }

    /**
     * @param  Collection<int, ToolRun>  $runs  آخر تشغيل لكل أداة (unique tool_code)
     * @return array{problems: array<int, array<string, mixed>>, covered: array<int, string>, missing: array<int, string>}
     */
    public function build(Collection $runs): array
    {
        $in = $this->gather($runs);

        $problems = array_values(array_filter([
            $this->goalVsReach($in),
            $this->messageGap($in),
            $this->noRiskReversal($in),
            $this->noRetention($in),
            $this->vagueSegment($in),
            $this->claimWithoutProof($in),
            $this->channelScatter($in),
            $this->noLeadingMetric($in),
            $this->pricingObjectionUnhandled($in),
        ]));

        // ترتيب حسب الشدّة: حرج ثم متوسط ثم منخفض.
        $rank = ['high' => 0, 'mid' => 1, 'low' => 2];
        usort($problems, fn (array $a, array $b): int => ($rank[$a['severity']] ?? 3) <=> ($rank[$b['severity']] ?? 3));

        return [
            'problems' => $problems,
            'covered' => array_keys($in['_tools']),
            'missing' => $this->missingDomains($in),
        ];
    }

    /**
     * يجمع إجابات كل الأدوات في خريطة مسطّحة key ⇒ قيمة (من inputs_json).
     *
     * @param  Collection<int, ToolRun>  $runs
     * @return array<string, mixed>
     */
    private function gather(Collection $runs): array
    {
        $flat = ['_tools' => []];
        foreach ($runs as $run) {
            $inputs = (array) ($run->inputs_json ?? []);
            $flat['_tools'][$run->tool_code] = true;
            foreach ($inputs as $key => $value) {
                if (is_string($value) && trim($value) !== '') {
                    // أول إجابة غير فارغة تفوز (آخر تشغيل مرّر أولاً).
                    $flat[$key] = $flat[$key] ?? trim($value);
                }
            }
        }

        return $flat;
    }

    private function val(array $in, string $key): string
    {
        return (string) ($in[$key] ?? '');
    }

    private function has(array $in, string $key): bool
    {
        return $this->val($in, $key) !== '';
    }

    /** هل أُنجزت الأداة المصدر فعلاً؟ (وجود ToolRun لها) — لتمييز «حقل فارغ» عن «أداة لم تُملأ». */
    private function toolDone(array $in, string ...$codes): bool
    {
        $tools = (array) ($in['_tools'] ?? []);
        foreach ($codes as $c) {
            if (! empty($tools[$c])) {
                return true;
            }
        }

        return false;
    }

    /** إجابة عامة: قصيرة جداً أو تحتوي حشواً (معجمياً أو دلالياً). */
    private function isGeneric(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return true;
        }
        foreach (self::FILLER as $f) {
            if (mb_stripos($value, $f) !== false) {
                return true;
            }
        }
        // فهم دلالي: يلتقط الحشو ولو بصياغة غير مقنّنة في القائمة أعلاه.
        if ($this->matcher->expresses($value, 'filler')) {
            return true;
        }

        return mb_strlen($value) < 12 || count(preg_split('/\s+/u', $value) ?: []) <= 2;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if (mb_stripos($haystack, $n) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{problem: string, cause: string, solution: string, severity: string, impact: string, evidence: array<int, string>}
     */
    private function make(string $problem, string $cause, string $solution, string $severity, string $impact, array $evidence, bool $deferred = false): array
    {
        return compact('problem', 'cause', 'solution', 'severity', 'impact', 'evidence', 'deferred');
    }

    // ═══════════ الكواشف المتقاطعة ═══════════

    /**
     * الهدف مقابل الوصول: هدف طموح (رقم + نمو/اكتساب) فوق قاعدة وصول ضعيفة
     * (لا قناة مثبتة + خوف انتشار في الضعف/التهديد/العائق). أخطر تناقض في ملف مبتدئ.
     */
    private function goalVsReach(array $in): ?array
    {
        $goal = $this->val($in, 'goal_now') ?: $this->val($in, 'goal_metric');
        if ($goal === '' || $this->isGeneric($goal)) {
            return null;
        }
        // هدف طموح: يحوي رقماً + كلمة اكتساب/نمو/مبيعات.
        if (! preg_match('/\d/u', $goal)
            || ! $this->containsAny($goal, ['عميل', 'عملاء', 'مبيعات', 'زيادة', 'نمو', '%', '٪', 'إيراد', 'ربح'])) {
            return null;
        }
        $hasChannel = $this->has($in, 'channel_primary') && ! $this->isGeneric($this->val($in, 'channel_primary'));
        $reachFear = trim($this->val($in, 'weaknesses').' '.$this->val($in, 'threats').' '.$this->val($in, 'goal_obstacle'));
        $weakReach = $this->containsAny($reachFear, ['انتشار', 'الإنتشار', 'الانتشار', 'يعرف', 'معرف', 'وصول', 'جمهور', 'ظهور', 'زيارات', 'حضور']);
        if ($hasChannel || ! $weakReach) {
            return null;
        }

        return $this->make(
            'هدفك أكبر من وصولك الحالي — فجوة بين الطموح والواقع.',
            'حدّدت هدفاً طموحاً: «'.$goal.'». لكن مؤشّراتك تكشف ضعف وصول: «'.$reachFear.'»، وبلا قناة أساسية مثبتة بعد. الهدف الطموح فوق قاعدة وصول ضعيفة يتحوّل إلى إحباط لا نتيجة.',
            'قبل ملاحقة الرقم الكبير، ثبّت قناة وصول واحدة وابنِ حضوراً أوّلياً: هدف مرحلي أصغر (أول 10 عملاء عبر قناة واحدة خلال 30 يوماً)، ثم توسّع نحو الرقم الأكبر بالبيانات.',
            'high',
            'الطموح بلا وصول يستنزف الوقت والميزانية قبل أوّل نتيجة.',
            ['goal_now (طموح)', 'channel_primary (فارغ)', 'weaknesses/threats (ضعف وصول)'],
        );
    }

    /** فجوة الرسالة: يعرف ميزته (منافسين) لكن تموضعه/جملته عامة. */
    private function messageGap(array $in): ?array
    {
        $advantage = $this->val($in, 'own_advantage') ?: $this->val($in, 'unique_angle') ?: $this->val($in, 'biggest_strength');
        if ($advantage === '' || $this->isGeneric($advantage)) {
            return null;
        }
        $message = $this->val($in, 'main_difference') ?: $this->val($in, 'positioning_statement') ?: $this->val($in, 'end_result');
        if ($message !== '' && ! $this->isGeneric($message)) {
            return null; // الرسالة تُبرز التميّز فعلاً — لا مشكلة.
        }

        return $this->make(
            'رسالتك التسويقية لا تُبرز تميّزك الحقيقي.',
            'في أداة تحليل المنافسين حدّدت ميزتك: «'.$advantage.'». لكن تموضعك/جملتك التعريفية عامة أو غير مبنية عليها — فجوة بين ما تعرفه وما تقوله. السبب ليس المنتج، بل ترجمة التميّز إلى رسالة.',
            'أعد صياغة جملتك التعريفية لتقول «'.$advantage.'» صراحةً، وضعها في: عنوان الصفحة، أول 3 ثوانٍ من كل إعلان، والـbio. اجعلها السبب الأول للشراء منك.',
            'high',
            'كل ريال إعلاني حالياً يجذب زائراً لا يفهم لماذا أنت الأفضل.',
            ['own_advantage/unique_angle', 'positioning/tagline'],
        );
    }

    /** لا عكس مخاطرة عند القرار مع وجود تردّد/اعتراض. */
    private function noRiskReversal(array $in): ?array
    {
        $guarantee = $this->val($in, 'offer_guarantee');
        if ($guarantee !== '' && ! $this->isGeneric($guarantee)) {
            return null;
        }
        $hesitation = $this->val($in, 'journey_doubt').' '.$this->val($in, 'journey_trust').' '.$this->val($in, 'main_objection').' '.$this->val($in, 'funnel_blocker');
        $hasHesitation = $this->containsAny($hesitation, ['ثقة', 'تردد', 'تردّد', 'شك', 'خوف', 'سعر', 'مخاطرة', 'ضمان', 'دفع'])
            || $this->matcher->expresses($hesitation, 'hesitation');
        if (trim($hesitation) === '' || ! $hasHesitation) {
            return null;
        }

        return $this->make(
            'العميل يتردّد عند القرار ولا شيء يطمئنه.',
            'رحلة العميل/الاعتراضات تُظهر تردّداً بسبب الثقة أو السعر: «'.trim($hesitation).'». وفي أداة العرض لا يوجد ضمان أو عكس مخاطرة عند لحظة القرار — فالتردّد يتحوّل إلى مغادرة.',
            'أضِف عكس مخاطرة واضحاً عند الدفع: ضمان إرجاع خلال 14 يوماً + الدفع عند الاستلام + شارة دفع آمن قرب الزر، وبسّط خطوات الدفع.',
            'high',
            'أعلى نقطة تسرّب في القمع؛ معالجتها ترفع التحويل مباشرة.',
            ['journey_doubt/objection', 'offer_guarantee (فارغ)'],
        );
    }

    /** لا آلية احتفاظ. */
    private function noRetention(array $in): ?array
    {
        $retention = $this->val($in, 'ladder_retention').$this->val($in, 'journey_retention').$this->val($in, 'followup_goal');
        if (trim($retention) !== '') {
            return null;
        }

        $done = $this->toolDone($in, 'value-ladder', 'follow-up-sequence', 'customer-journey');
        $solution = 'صمّم سلّم قيمة (عيّنة → منتج أساسي → اشتراك/تجديد) + تسلسل متابعة بعد البيع (شكر → قيمة → عرض تكرار). ابدأ برسالة متابعة واحدة بعد 7 أيام من الشراء.';
        $impact = 'رفع تكرار الشراء أرخص 5 مرّات من اكتساب عميل جديد.';

        if (! $done) {
            return $this->make(
                'لم تُحدِّد بعد آلية للاحتفاظ بالعميل بعد أوّل شراء.',
                'أدوات سلّم القيمة والمتابعة ورحلة العميل لم تُنجَز بعد، فلا تظهر أي آلية احتفاظ. قد تكون واضحة في ذهنك لكنها غير مُدوّنة — وتركها فارغة يجعل النموّ كلّه متّكئاً على اكتساب جديد مكلف.',
                $solution, 'mid', $impact, ['أدوات الاحتفاظ لم تُنجَز بعد'], true,
            );
        }

        return $this->make(
            'تشتري العميل مرة واحدة ثم تفقده.',
            'أنجزت أدوات سلّم القيمة/المتابعة/رحلة العميل لكن بلا أي آلية احتفاظ فيها. كل نموّك يعتمد على اكتساب جديد مكلف بدل تكرار الشراء.',
            $solution, 'mid', $impact, ['ladder_retention/followup (فارغ)'],
        );
    }

    /** شريحة غامضة. */
    private function vagueSegment(array $in): ?array
    {
        $customer = $this->val($in, 'customer_type');
        if ($customer === '' || ! $this->isGeneric($customer)) {
            return null;
        }

        return $this->make(
            'جمهورك المستهدف غير محدّد بدقّة.',
            'إجابتك عن العميل المثالي عامة: «'.$customer.'». لا يمكن بناء رسالة أو حملة على «الجميع» — الإعلان يُوزَّع بلا تركيز فترتفع تكلفة الاكتساب.',
            'حدّد شريحة أولى ملموسة: الفئة العمرية + المدينة + دافع الشراء (لنفسها أم هدية؟) + الاعتراض الأساسي. ابدأ بها ثم توسّع بناءً على النتائج.',
            'high',
            'إنفاق غير موجّه = تكلفة اكتساب مرتفعة وتحويل منخفض.',
            ['customer_type (عام)'],
        );
    }

    /** وعد بلا دليل. */
    private function claimWithoutProof(array $in): ?array
    {
        $result = $this->val($in, 'offer_result') ?: $this->val($in, 'promise_result');
        if ($result === '' || $this->isGeneric($result)) {
            return null;
        }
        $proof = $this->val($in, 'proof_point') ?: $this->val($in, 'promise_proof');
        if ($proof !== '' && ! $this->isGeneric($proof)) {
            return null;
        }

        return $this->make(
            'تعِد بنتيجة لكن بلا دليل يثبتها.',
            'عرضك يعِد بـ«'.$result.'» لكن لا يوجد دليل مقابل (شهادة، رقم، دراسة حالة) في أدوات العرض/الوعد. الوعد بلا دليل يزيد الشكّ لا الثقة.',
            'اربط كل وعد بدليل ملموس بجانبه: عدد عملاء، نتيجة موثّقة، أو شهادة حقيقية. اجمع 3 شهادات هذا الأسبوع واعرضها قرب الوعد.',
            'mid',
            'الدليل يحوّل الوعد من ادّعاء إلى سبب شراء.',
            ['offer_result (وعد)', 'proof_point (فارغ)'],
        );
    }

    /** تشتّت القنوات. */
    private function channelScatter(array $in): ?array
    {
        if ($this->has($in, 'channel_primary') && ! $this->isGeneric($this->val($in, 'channel_primary'))) {
            return null;
        }
        if (! $this->has($in, 'plan_goal') && ! $this->has($in, 'best_channel')) {
            return null;
        }

        $done = $this->toolDone($in, 'marketing-plan');
        $solution = 'اختر قناة واحدة يتواجد فيها جمهورك أكثر، وجّه إليها الجهد الأكبر حتى تحقّق نتيجة مستقرّة، ثم توسّع.';
        $impact = 'تجميع الجهد في قناة واحدة يسرّع التعلّم ويخفض تكلفة الاكتساب.';

        if (! $done) {
            return $this->make(
                'لم تُحدِّد بعد قناة أساسية تركّز عليها.',
                'خطتك التسويقية لم تُنجَز بعد، فلا قناة أساسية مُثبتة. توزيع الجهد على قنوات متعددة قبل إتقان واحدة يبعثر الميزانية والتعلّم.',
                $solution, 'low', $impact, ['قناة أساسية غير محدّدة بعد'], true,
            );
        }

        return $this->make(
            'لا قناة أساسية واضحة تركّز عليها.',
            'خطتك التسويقية لا تحدّد قناة أساسية واحدة رغم إنجازها. توزيع الجهد على قنوات متعددة قبل إتقان واحدة يبعثر الميزانية والتعلّم.',
            $solution, 'low', $impact, ['channel_primary (فارغ)'],
        );
    }

    /** لا مؤشّر قائد. */
    private function noLeadingMetric(array $in): ?array
    {
        $metric = $this->val($in, 'kpi_leading').$this->val($in, 'north_metric').$this->val($in, 'funnel_metric');
        if (trim($metric) !== '') {
            return null;
        }

        $done = $this->toolDone($in, 'kpi-tracker', 'marketing-plan', 'funnel-builder');
        $solution = 'حدّد مؤشّراً قائداً واحداً (مثل معدّل التحويل) + عتبة إنذار + إجراء عند تجاوزها، وراقبه أسبوعياً على لوحة واحدة.';
        $impact = 'بلا قياس، الميزانية تُنفَق على الحدس لا على ما ينجح.';

        if (! $done) {
            return $this->make(
                'لم تُحدِّد بعد مؤشّراً قائداً تقيس به تقدّمك.',
                'أدوات المؤشرات/الخطة/القمع لم تُنجَز بعد، فلا يظهر مقياس واحد تراقبه. بلا مقياس محدّد، لن تعرف هل تتقدّم أم تدور.',
                $solution, 'mid', $impact, ['مؤشّر قائد غير محدّد بعد'], true,
            );
        }

        return $this->make(
            'تعمل بلا مؤشّر قياس تقود به قراراتك.',
            'أنجزت أدوات المؤشرات/الخطة/القمع لكن بلا مؤشّر قائد محدّد فيها. بلا مقياس واحد تراقبه، لن تعرف هل تتقدّم أم تدور.',
            $solution, 'mid', $impact, ['kpi_leading/north_metric (فارغ)'],
        );
    }

    /** اعتراض سعر بلا معالجة. */
    private function pricingObjectionUnhandled(array $in): ?array
    {
        $objection = $this->val($in, 'pricing_objection');
        if ($objection === '' || $this->isGeneric($objection)) {
            return null;
        }
        if ($this->has($in, 'pricing_anchor') && ! $this->isGeneric($this->val($in, 'pricing_anchor'))) {
            return null;
        }

        return $this->make(
            'اعتراض السعر موجود بلا معالجة.',
            'حدّدت اعتراض السعر: «'.$objection.'» لكن لا توجد نقطة مقارنة (anchor) تجعل سعرك يبدو منطقياً قبل ذكره.',
            'أنشئ نقطة مقارنة تُعرَض قبل السعر (سعر المستورد، أو تكلفة البديل الأرخص الذي لا يدوم)، فيبدو سعرك القيمة الأعقل.',
            'low',
            'تأطير السعر بمقارنة يقلّل حساسية السعر ويرفع القبول.',
            ['pricing_objection', 'pricing_anchor (فارغ)'],
        );
    }

    /**
     * @return array<int, string>
     */
    private function missingDomains(array $in): array
    {
        $checks = [
            'العميل المثالي' => 'customer_type',
            'التموضع/الرسالة' => 'main_difference',
            'العرض' => 'offer_result',
            'رحلة العميل' => 'journey_friction',
            'المؤشرات' => 'kpi_leading',
        ];
        $missing = [];
        foreach ($checks as $label => $key) {
            if (! $this->has($in, $key)) {
                $missing[] = $label;
            }
        }

        return $missing;
    }
}
