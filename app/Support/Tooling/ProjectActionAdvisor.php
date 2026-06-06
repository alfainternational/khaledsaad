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
                'reason' => 'ملف المشروع ما زال ناقصاً، وأفضل خطوة الآن هي إكمال معلوماته قبل تشغيل أدوات إضافية.',
                'recommended_tool_code' => null,
                'recommended_tool_label' => 'تعديل ملف المشروع',
                'action_type' => 'brief',
                'priority' => 'critical',
            ];
        }

        if (! in_array('diagnosis', $toolCodes, true)) {
            return $this->toolAction('diagnosis', 'ابدأ بالتشخيص', 'معلوماتك كافية ليعطيك التشخيص قراراً أوضح وأولوية تنفيذ أقرب.');
        }

        if (! in_array('ideal-customer', $toolCodes, true) && trim((string) data_get($brief, 'audience.ideal_customer', '')) === '') {
            return $this->toolAction('ideal-customer', 'حدّد صورة عميلك', 'الخطوة التالية هي تحويل وصف عميلك إلى صورة أوضح قبل الرسائل والعرض.');
        }

        if (! in_array('positioning', $toolCodes, true) && $this->missingPositioningSignal($brief)) {
            return $this->toolAction('positioning', 'وضّح ما يميّزك', 'مشروعك يحتاج صياغة أوضح للفرق الذي يجب أن يراه العميل قبل توسيع الخطة أو المحتوى.');
        }

        if (! in_array('offer-builder', $toolCodes, true)) {
            return $this->toolAction('offer-builder', 'حوّل الفهم إلى عرض', 'صار عميلك وهدفك واضحين بما يكفي لتحويلهما إلى عرض يمكن بيعه وتسليمه.');
        }

        if (! in_array('marketing-plan', $toolCodes, true)) {
            return $this->toolAction('marketing-plan', 'ابنِ الخطة التسويقية', 'بعد العرض، الأفضل الآن تثبيت العميل والقناة والرسالة في خطة تنفيذية قريبة.');
        }

        if (! in_array('content-plan', $toolCodes, true)) {
            return $this->toolAction('content-plan', 'نظّم المحتوى', 'الخطة موجودة، والخطوة التالية هي تحويلها إلى محتوى يخدم القناة والرسالة.');
        }

        return [
            'headline' => 'مشروعك جاهز للنتائج والتنفيذ',
            'reason' => 'أساسك جيد بما يكفي للانتقال إلى الاستوديو أو الحملات أو مراجعة النتائج بدل جمع معلومات إضافية.',
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
