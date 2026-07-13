<?php

namespace App\Support\Tooling;

class ToolDisplayCatalog
{
    /**
     * @return array<string, array{label: string, short: string}>
     */
    public static function all(): array
    {
        return [
            'diagnosis' => ['label' => 'التشخيص', 'short' => 'اعرف أين تقف الآن وما الأولوية الأقرب.'],
            'idea-clarity' => ['label' => 'وضوح الفكرة', 'short' => 'حوّل الفكرة إلى وصف واضح قابل للفهم.'],
            'goal-definition' => ['label' => 'تحديد الهدف', 'short' => 'ثبّت الهدف التجاري الأقرب قبل اختيار الأدوات.'],
            'problem-definition' => ['label' => 'تحديد المشكلة', 'short' => 'حدّد المشكلة التي يدفع العميل لحلها.'],
            'swot-analysis' => ['label' => 'تحليل القوة والضعف', 'short' => 'افهم القوة والضعف والفرص والمخاطر.'],
            'tagline-builder' => ['label' => 'الجملة التعريفية', 'short' => 'اكتب وصفاً مختصراً يسهل تذكره.'],
            'ideal-customer' => ['label' => 'العميل المثالي', 'short' => 'حدّد من تريد جذبه فعلاً.'],
            'positioning' => ['label' => 'التمركز', 'short' => 'وضّح لماذا يختارك العميل دون غيرك.'],
            'market-analysis' => ['label' => 'تحليل السوق', 'short' => 'اقرأ السوق والفرص القريبة.'],
            'competitor-analysis' => ['label' => 'تحليل المنافسين', 'short' => 'اعرف كيف تقارن نفسك بالمنافسين.'],
            'offer-builder' => ['label' => 'بناء العرض', 'short' => 'حوّل القيمة إلى عرض قابل للبيع.'],
            'pricing-strategy' => ['label' => 'استراتيجية التسعير', 'short' => 'اختر منطق تسعير واضح ومقنع.'],
            'value-ladder' => ['label' => 'سلم القيمة', 'short' => 'رتّب عروضك من البداية إلى النمو.'],
            'package-builder' => ['label' => 'بناء الباقات', 'short' => 'حوّل العرض إلى باقات سهلة الاختيار.'],
            'promise-builder' => ['label' => 'الوعد التسويقي', 'short' => 'اكتب الوعد الذي يفهمه العميل ويثق به.'],
            'funnel-builder' => ['label' => 'مسار التحويل', 'short' => 'ارسم رحلة العميل من الاهتمام إلى الشراء.'],
            'customer-journey' => ['label' => 'رحلة العميل', 'short' => 'افهم نقاط الاحتكاك قبل وبعد الشراء.'],
            'marketing-plan' => ['label' => 'الخطة التسويقية', 'short' => 'حوّل المعرفة إلى خطة قنوات وحملات.'],
            'content-plan' => ['label' => 'خطة المحتوى', 'short' => 'نظّم المحتوى حسب الرسالة والقناة.'],
            'campaign-builder' => ['label' => 'بناء الحملات', 'short' => 'جهّز حملة برسالة وجمهور وقياس.'],
            'follow-up-sequence' => ['label' => 'تسلسل المتابعة', 'short' => 'رتّب رسائل المتابعة حتى لا تضيع الفرص.'],
            'kpi-tracker' => ['label' => 'مؤشرات الأداء', 'short' => 'حدّد ما ستقيسه حتى تعرف هل التسويق يعمل.'],
            'execution-plan' => ['label' => 'خطة التنفيذ', 'short' => 'حوّل القرارات إلى مهام ومسؤوليات.'],
            'performance-review' => ['label' => 'مراجعة الأداء', 'short' => 'اقرأ النتائج واعرف أين الخلل.'],
            'agency-audit' => ['label' => 'تقييم عمل الوكالة', 'short' => 'افهم هل وعود الوكالة وتقاريرها منطقية.'],
            'smart-recommendations' => ['label' => 'التوصيات الذكية', 'short' => 'احصل على خطوات تحسين مرتبة حسب الأثر.'],
            'growth-priorities' => ['label' => 'أولويات النمو', 'short' => 'اختر أين تضع الجهد التالي لزيادة الدخل.'],
        ];
    }

    public static function label(string $code, ?string $fallback = null): string
    {
        return static::all()[$code]['label'] ?? ($fallback ?: $code);
    }

    public static function shortDescription(string $code, ?string $fallback = null): string
    {
        return static::all()[$code]['short'] ?? ($fallback ?: '');
    }
}
