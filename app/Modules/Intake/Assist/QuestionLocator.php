<?php

namespace App\Modules\Intake\Assist;

use App\Models\ConsultationSession;
use App\Models\Project;
use App\Models\QuestionAssist;
use App\Models\QuestionVersion;
use App\Models\ToolRun;
use App\Support\Marketing\BriefQuestions;

/**
 * تحويل «مفتاح سؤال + السياق الذي يراه المستخدم» إلى وصف سؤال موحّد.
 *
 * المفتاح وحده لا يكفي ولم يكن يومًا: `active_channels` في أداة قائمةِ قنوات
 * سؤالٌ عن أسماء المنصات، وفي أداة أخرى سؤالٌ عن عددها بخيارات أخرى تمامًا —
 * وهي مشكلة سبق أن ظهرت في `ToolPresenter` وعُلّقت هناك. لذلك يُطلب معه معرّف
 * التشغيل أو الجلسة: منه وحده يُعرف **أي** سؤال يقصد المستخدم.
 *
 * بلا هذا الحد كان الدليل قد يُبنى على خيارات أداة أخرى، فيُرشَّح للمستخدم خيار
 * غير موجود في شاشته.
 */
class QuestionLocator
{
    /**
     * سؤال داخل تشغيل أداة.
     */
    public function inToolRun(ToolRun $run, string $questionKey): ?QuestionDescriptor
    {
        $run->loadMissing('toolVersion.fields');

        $field = $run->toolVersion->fields->firstWhere('key', $questionKey);

        return $field === null ? null : QuestionDescriptor::fromToolField($field);
    }

    /**
     * سؤال داخل جلسة استشارة.
     */
    public function inConsultation(ConsultationSession $session, string $questionKey): ?QuestionDescriptor
    {
        $session->loadMissing('blueprintVersion.modules.questions.questionVersion.definition');

        $version = $session->blueprintVersion->modules
            ->flatMap(fn ($module) => $module->questions)
            ->map(fn ($binding) => $binding->questionVersion)
            ->filter()
            ->first(fn (QuestionVersion $candidate) => $candidate->definition?->key === $questionKey);

        if ($version === null) {
            return null;
        }

        return QuestionDescriptor::fromConsultationQuestion(
            questionKey: $version->definition->key,
            fieldKey: $version->definition->internal_variable,
            text: $version->user_text,
            help: $version->help_text,
            why: $version->why_text,
            type: $version->answer_type,
            options: $this->options($version),
            required: (bool) $version->required,
        );
    }

    /**
     * سؤال في موجز الوكالة.
     *
     * لا تشغيل ولا جلسة يحدّان أيّ سؤال: أسئلة الموجز ساكنة في `BriefQuestions`
     * ومفاتيحها فريدة، فالمفتاح وحده يكفي لتحديدها. الحدّ الوحيد أن السؤال من
     * الموجز فعلًا لا مفتاح مخترع.
     */
    public function inAgencyBrief(string $questionKey): ?QuestionDescriptor
    {
        $field = collect(BriefQuestions::fields())->firstWhere('key', $questionKey);

        return $field === null ? null : QuestionDescriptor::fromBriefField($field);
    }

    /**
     * سؤال في ملف المشروع.
     *
     * كالموجز: لا تشغيل ولا جلسة يحدّانه، ومفاتيح `ProfileQuestions` فريدة
     * فالمفتاح وحده يكفي. والحدّ أن يكون السؤال من الملف فعلًا لا مفتاحًا مخترعًا.
     */
    public function inProfile(string $questionKey): ?QuestionDescriptor
    {
        $field = ProfileQuestions::find($questionKey);

        return $field === null ? null : QuestionDescriptor::fromProfileField($field);
    }

    /**
     * السطح المسموح به لكل سياق — يُستعمل في التحقق من الطلب.
     *
     * @return array<int, string>
     */
    public static function surfaces(): array
    {
        return [
            QuestionAssist::SURFACE_CONSULTATION,
            QuestionAssist::SURFACE_TOOL,
            QuestionAssist::SURFACE_AGENCY,
            QuestionAssist::SURFACE_PROFILE,
        ];
    }

    /**
     * ملكية المشروع تُتحقَّق عند المتحكّم؛ هنا نتأكد أن السياق يخصّ المشروع
     * نفسه. تشغيلٌ لمشروع آخر بمفتاح سؤال صحيح كان سيبني دليلًا على نشاط غريب.
     */
    public function belongsTo(Project $project, ToolRun|ConsultationSession $context): bool
    {
        return $context->project_id === $project->id;
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function options(QuestionVersion $version): array
    {
        if ($version->answer_type === 'boolean' && empty($version->options)) {
            return [['value' => '1', 'label' => 'نعم'], ['value' => '0', 'label' => 'لا']];
        }

        return array_map(
            fn (array $option) => [
                'value' => $option['value'] ?? '',
                'label' => (string) ($option['label'] ?? $option['value'] ?? ''),
            ],
            $version->options ?? [],
        );
    }
}
