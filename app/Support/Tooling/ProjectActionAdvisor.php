<?php

namespace App\Support\Tooling;

use App\Domain\Project\Models\Project;

class ProjectActionAdvisor
{
    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $briefAssessment
     * @param  array<string, mixed>  $journeySnapshot
     * @param  array<int, array<string, mixed>>  $toolSummaries
     * @return array<string, mixed>
     */
    public function advise(
        Project $project,
        array $brief,
        array $briefAssessment,
        array $journeySnapshot = [],
        array $toolSummaries = [],
    ): array {
        $briefScore = (int) ($briefAssessment['completeness_score'] ?? 0);
        $toolCodes = collect($toolSummaries)->pluck('tool_code')->filter()->values()->all();

        if ($briefScore < 45) {
            return [
                'headline' => 'أكمل ملف المشروع أولاً',
                'reason' => 'الـ brief ما زال ناقصاً، وأقوى مردود الآن يأتي من إغلاق فجوات السياق قبل تشغيل أدوات إضافية.',
                'recommended_tool_code' => null,
                'recommended_tool_label' => 'تحرير brief المشروع',
                'action_type' => 'brief',
                'priority' => 'critical',
            ];
        }

        if (! in_array('diagnosis', $toolCodes, true)) {
            return $this->toolAction('diagnosis', 'ابدأ بالتشخيص', 'لديك سياق كافٍ ليخرج التشخيص بقرار أوضح وأولوية تنفيذ أقرب.');
        }

        if (! in_array('ideal-customer', $toolCodes, true) && trim((string) data_get($brief, 'audience.ideal_customer', '')) === '') {
            return $this->toolAction('ideal-customer', 'ثبّت صورة العميل', 'الخطوة التالية المنطقية هي تحويل وصف الجمهور إلى ملف شراء أوضح قبل الرسائل والعرض.');
        }

        if (! in_array('positioning', $toolCodes, true) && $this->missingPositioningSignal($brief)) {
            return $this->toolAction('positioning', 'وضّح التمركز', 'المشروع يحتاج صياغة أوضح للفارق الذي يجب أن يراه العميل قبل توسيع الخطة أو المحتوى.');
        }

        if (! in_array('offer-builder', $toolCodes, true)) {
            return $this->toolAction('offer-builder', 'حوّل الفهم إلى عرض', 'الجمهور والهدف واضحان بما يكفي لتحويلهما إلى عرض يمكن بيعه وتوصيله.');
        }

        if (! in_array('marketing-plan', $toolCodes, true)) {
            return $this->toolAction('marketing-plan', 'ابنِ الخطة التسويقية', 'بعد العرض، الأفضل الآن تثبيت الشريحة والقناة والرسالة في خطة تنفيذية قريبة.');
        }

        if (! in_array('content-plan', $toolCodes, true)) {
            return $this->toolAction('content-plan', 'نظّم المحتوى', 'الخطة موجودة، والخطوة التالية المنطقية هي ترجمتها إلى محتوى يخدم القناة والرسالة.');
        }

        return [
            'headline' => 'المشروع جاهز للمخرجات والتنفيذ',
            'reason' => 'الأساس الاستراتيجي جيد بما يكفي للانتقال إلى الاستوديو، الحملات، أو مراجعة الأداء بدل جمع معلومات إضافية.',
            'recommended_tool_code' => null,
            'recommended_tool_label' => 'استخدم الاستوديو أو راجع التقارير',
            'action_type' => 'studio',
            'priority' => 'ready',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolAction(string $code, string $headline, string $reason): array
    {
        return [
            'headline' => $headline,
            'reason' => $reason,
            'recommended_tool_code' => $code,
            'recommended_tool_label' => $this->toolLabel($code),
            'action_type' => 'tool',
            'priority' => 'important',
        ];
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    private function missingPositioningSignal(array $brief): bool
    {
        return trim((string) data_get($brief, 'positioning.edge', '')) === ''
            && trim((string) data_get($brief, 'competition.gap', '')) === '';
    }

    private function toolLabel(string $code): string
    {
        return match ($code) {
            'diagnosis' => 'التشخيص',
            'ideal-customer' => 'العميل المثالي',
            'positioning' => 'التمركز',
            'offer-builder' => 'بناء العرض',
            'marketing-plan' => 'الخطة التسويقية',
            'content-plan' => 'خطة المحتوى',
            default => $code,
        };
    }
}
