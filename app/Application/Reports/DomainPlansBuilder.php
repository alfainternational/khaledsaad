<?php

namespace App\Application\Reports;

use App\Domain\Tool\Models\ToolRun;
use Illuminate\Support\Collection;

/**
 * باني خطط المجالات — يحوّل إجابات المستخدم إلى خطط كاملة قابلة للتطبيق لكل مجال
 * (محتوى · ترويج · تطوير العرض · رحلة العميل · متابعة الأداء)، لا نقاط.
 *
 * كل بند مشتقّ من مدخل حقيقي حيثما وُجد؛ وحيث ينقص، يُقدَّم توصية ملموسة موسومة
 * بـ«مقترح». محلي بالكامل، حتمي. النظير لكل خطة أخصائي من الـ25.
 */
class DomainPlansBuilder
{
    /**
     * @param  Collection<int, ToolRun>  $runs
     * @return array<string, array{title: string, by: string, goal: string, sections: array<int, array{heading: string, items: array<int, string>}>}>
     */
    public function build(Collection $runs): array
    {
        $in = $this->gather($runs);

        return [
            'content' => $this->contentPlan($in),
            'promotion' => $this->promotionPlan($in),
            'offer' => $this->offerPlan($in),
            'journey' => $this->journeyPlan($in),
            'performance' => $this->performancePlan($in),
        ];
    }

    /**
     * @param  Collection<int, ToolRun>  $runs
     * @return array<string, string>
     */
    private function gather(Collection $runs): array
    {
        $flat = [];
        foreach ($runs as $run) {
            foreach ((array) ($run->inputs_json ?? []) as $key => $value) {
                if (is_string($value) && trim($value) !== '') {
                    $flat[$key] = $flat[$key] ?? trim($value);
                }
            }
        }

        return $flat;
    }

    private function g(array $in, string ...$keys): string
    {
        foreach ($keys as $k) {
            if (($in[$k] ?? '') !== '') {
                return (string) $in[$k];
            }
        }

        return '';
    }

    /** بند مشتقّ من مدخل، أو توصية موسومة «مقترح» عند غيابه. */
    private function line(string $value, string $whenPresent, string $whenMissing): string
    {
        return $value !== '' ? str_replace('{v}', $value, $whenPresent) : 'مقترح: '.$whenMissing;
    }

    /**
     * @return array{title: string, by: string, goal: string, sections: array<int, array{heading: string, items: array<int, string>}>}
     */
    private function plan(string $title, string $by, string $goal, array $sections): array
    {
        return compact('title', 'by', 'goal', 'sections');
    }

    // ═══════════ خطط المجالات ═══════════

    private function contentPlan(array $in): array
    {
        $advantage = $this->g($in, 'own_advantage', 'unique_angle', 'main_difference', 'biggest_strength');
        $problem = $this->g($in, 'customer_problem', 'main_bottleneck');
        $proof = $this->g($in, 'proof_point', 'promise_proof');
        $channel = $this->g($in, 'best_channel', 'channel_primary');
        $topics = $this->g($in, 'content_topics');

        return $this->plan('خطة المحتوى', 'content-creator · social-media-manager · seo-specialist',
            $this->g($in, 'content_goal') ?: 'إثبات تميّزك وبناء الثقة قبل الطلب — كل قطعة تخدم مرحلة في القمع.',
            [
                ['heading' => 'ركائز المحتوى', 'items' => array_values(array_filter([
                    $this->line($advantage, 'ركيزة الإثبات: محتوى يُظهر «{v}» بصرياً (قبل/بعد، تجربة حيّة).', 'ركيزة إثبات تُظهر ميزتك الأساسية بصرياً — حدّد ميزتك أولاً في أداة المنافسين.'),
                    $this->line($problem, 'ركيزة تعليمية تعالج «{v}» وتبني الوعي.', 'ركيزة تعليمية تعالج مشكلة عميلك — حدّدها في أداة العميل المثالي.'),
                    $this->line($proof, 'ركيزة دليل اجتماعي: «{v}» + مراجعات ومحتوى مستخدم.', 'ركيزة دليل اجتماعي (شهادات، مراجعات) — اجمع 3 شهادات هذا الأسبوع.'),
                    'ركيزة عروض/مناسبات: تجميعات وعروض موسمية لمرحلة القرار.',
                ]))],
                ['heading' => 'الإيقاع والقنوات', 'items' => array_values(array_filter([
                    '4–5 قطع أسبوعياً، وإعادة استخدام كل قطعة عبر 3 منصّات.',
                    $this->line($channel, 'القناة الأساسية: «{v}» — ركّز الجهد الأكبر فيها.', 'حدّد قناة أساسية واحدة يتواجد فيها جمهورك أكثر.'),
                    'الفيديو القصير محرّك أساسي إن كان التميّز بصرياً؛ والسيو (AEO) يلتقط نية الشراء من البحث.',
                    $topics !== '' ? 'مواضيع مبدئية من إجاباتك: '.$topics : 'مقترح: ابنِ أول 8 مواضيع حول ركيزة الإثبات.',
                ]))],
            ]);
    }

    private function promotionPlan(array $in): array
    {
        $goal = $this->g($in, 'campaign_goal', 'plan_goal');
        $customer = $this->g($in, 'customer_type');
        $channel = $this->g($in, 'campaign_channel', 'best_channel', 'channel_primary');
        $test = $this->g($in, 'campaign_test');

        return $this->plan('خطة الترويج والإعلان', 'media-buyer · campaign · funnel-architect',
            'لا نزيد الإنفاق قبل إصلاح الرسالة — نختبر الزاوية أولاً، ثم نوسّع الفائز بالبيانات.',
            [
                ['heading' => 'الهدف والجمهور', 'items' => array_values(array_filter([
                    $this->line($goal, 'هدف واحد للحملة: «{v}» — لا أهداف مبعثرة.', 'هدف واحد واضح للحملة (تحويل/ليدات) — لا تخلط الأهداف.'),
                    $this->line($customer, 'الجمهور: «{v}» + جمهور مشابه لمشترياتك.', 'حدّد الجمهور بدقّة (فئة/مدينة/دافع) قبل الإطلاق.'),
                    $this->line($channel, 'القناة: «{v}».', 'اختر القناة التي يتواجد فيها جمهورك أكثر.'),
                ]))],
                ['heading' => 'بنية الحملة والميزانية', 'items' => [
                    'وزّع الميزانية: ~45% تحويل · 25% وعي · 20% إعادة استهداف (هجر السلة) · 10% مناسبات.',
                    $this->line($test, 'اختبر عنصراً واحداً أولاً: «{v}» بميزانية صغيرة، ثم وسّع الفائز.', 'اختبر 3 زوايا (الميزة/السعر/الهدية) بميزانية صغيرة؛ وسّع الأفضل.'),
                    'ضع حدّاً أقصى للخسارة لكل إعلان تجريبي (مثلاً 300 ﷼) قبل الإيقاف.',
                    'راقب ROAS ≥ 3 كشرط للتوسّع.',
                ]],
            ]);
    }

    private function offerPlan(array $in): array
    {
        $offer = $this->g($in, 'offer_name', 'offer_result');
        $guarantee = $this->g($in, 'offer_guarantee');
        $entry = $this->g($in, 'entry_offer');
        $core = $this->g($in, 'core_offer');
        $premium = $this->g($in, 'premium_offer');
        $anchor = $this->g($in, 'pricing_anchor');

        return $this->plan('خطة تطوير العرض والتسعير', 'cro-specialist · offer · value-ladder',
            'رفع قوة العرض وتقليل مخاطرة العميل، وبناء سلّم يزيد قيمة العميل عبر الزمن.',
            [
                ['heading' => 'قوة العرض وعكس المخاطرة', 'items' => array_values(array_filter([
                    $offer !== '' ? 'العرض الحالي: '.$offer : 'مقترح: صُغ عرضاً بنتيجة واحدة واضحة وقابلة للإثبات.',
                    $this->line($guarantee, 'الضمان: «{v}» — أبرزه عند الدفع.', 'أضِف عكس مخاطرة (ضمان إرجاع 14 يوماً + دفع عند الاستلام) — أعلى رافعة للتحويل.'),
                    $this->line($anchor, 'نقطة المقارنة قبل السعر: «{v}».', 'أنشئ نقطة مقارنة (سعر البديل الأغلى/الأقل جودة) تُعرَض قبل سعرك.'),
                ]))],
                ['heading' => 'سلّم القيمة', 'items' => [
                    $this->line($entry, 'المدخل: «{v}» — منخفض المخاطرة يُثبت القيمة بسرعة.', 'أضِف عرضاً مدخلياً منخفض المخاطرة (عيّنة/تجربة) يُثبت القيمة.'),
                    $this->line($core, 'الأساسي: «{v}» — محرّك الربح.', 'حدّد العرض الأساسي (محرّك الربح الرئيسي).'),
                    $this->line($premium, 'المتقدّم: «{v}» — للعملاء الأوفياء.', 'أضِف عرضاً متقدّماً/اشتراكاً للاحتفاظ وتكرار الشراء.'),
                ]],
            ]);
    }

    private function journeyPlan(array $in): array
    {
        $friction = $this->g($in, 'journey_friction', 'funnel_blocker');
        $trust = $this->g($in, 'journey_trust');
        $doubt = $this->g($in, 'journey_doubt');
        $retention = $this->g($in, 'journey_retention', 'ladder_retention');

        return $this->plan('خطة رحلة العميل والتحويل', 'journey-orchestrator · cro-specialist',
            'إزالة أكبر نقاط الاحتكاك وبناء الثقة في اللحظات الحاسمة عبر الرحلة.',
            [
                ['heading' => 'نقاط الاحتكاك والثقة', 'items' => array_values(array_filter([
                    $this->line($friction, 'أكبر احتكاك: «{v}» — أولوية الإزالة.', 'حدّد أكبر نقطة يتوقّف عندها العميل (غالباً الدفع) وأزل احتكاكها.'),
                    $this->line($trust, 'لحظة بناء الثقة: «{v}» — عزّزها بدليل اجتماعي.', 'حدّد لحظة تبني فيها الثقة وأضِف إليها دليلاً اجتماعياً.'),
                    $this->line($doubt, 'لحظة الشك: «{v}» — جهّز محتوى يعالجها مباشرة.', 'حدّد لحظة الشكّ الأساسية وجهّز رداً/ضماناً عندها.'),
                ]))],
                ['heading' => 'الاحتفاظ بعد التحويل', 'items' => [
                    $this->line($retention, 'آلية الاحتفاظ: «{v}».', 'أضِف آلية احتفاظ (متابعة بعد البيع + برنامج تكرار/ولاء).'),
                    'أضِف تسلسل ما بعد البيع: شكر → قيمة → طلب مراجعة → عرض تكرار.',
                ]],
            ]);
    }

    private function performancePlan(array $in): array
    {
        $leading = $this->g($in, 'kpi_leading', 'north_metric', 'funnel_metric');
        $threshold = $this->g($in, 'kpi_threshold');
        $action = $this->g($in, 'kpi_action');

        return $this->plan('خطة متابعة الأداء', 'analytics-analyst · marketing-scientist · performance-monitor',
            'لكل هدف مؤشّر قائد وعتبة إنذار وإجراء عند الانحراف — لا مؤشرات للزينة.',
            [
                ['heading' => 'المؤشّر القائد والعتبات', 'items' => array_values(array_filter([
                    $this->line($leading, 'المؤشّر القائد: «{v}» — يُراقَب أسبوعياً.', 'حدّد مؤشّراً قائداً واحداً (مثل معدّل التحويل) يتنبّأ بالنتائج.'),
                    $this->line($threshold, 'عتبة الإنذار: «{v}».', 'حدّد عتبة إنذار: متى تعرف أن هناك مشكلة؟'),
                    $this->line($action, 'الإجراء عند الانحراف: «{v}».', 'جهّز إجراءً محدّداً عند تجاوز العتبة (لا تؤجّل).'),
                ]))],
                ['heading' => 'الإيقاع', 'items' => [
                    'لوحة أسبوعية للمؤشرات القائدة · مراجعة استراتيجية شهرية · تقرير للعميل كل شهر.',
                    'راقب: معدّل التحويل، هجر السلة، ROAS، تكرار الشراء — مع هدف وعتبة لكلٍّ.',
                ]],
            ]);
    }
}
