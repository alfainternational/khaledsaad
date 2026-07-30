<?php

namespace App\Modules\Intake\Assist;

use App\Models\QuestionAssist;
use App\Models\ToolField;
use App\Modules\Intake\Fitness\AnswerFitnessScorer;
use App\Modules\Intake\Fitness\FieldExpectation;

/**
 * وصف سؤال واحد بصورة لا تعرف من أي سطح جاء.
 *
 * سببه أن القاعدة تسري على **كل** سؤال في كل قالب واستمارة بلا استثناء. سؤال
 * الاستشارة (`QuestionVersion`) وحقل الأداة (`ToolField`) نموذجان مختلفان
 * تمامًا، ولو بُني الإرشاد على أحدهما لاحتاج السطح الثاني نسخةً ثانية منه —
 * فتتفرّق المقترحات إلى منطقين يتباعدان مع أول تعديل.
 */
final class QuestionDescriptor
{
    /**
     * @param  array<int, array{value: mixed, label: string}>  $options
     */
    private function __construct(
        public readonly string $surface,
        public readonly string $questionKey,
        public readonly string $fieldKey,
        public readonly string $text,
        public readonly ?string $help,
        public readonly ?string $why,
        public readonly string $type,
        public readonly array $options,
        public readonly bool $required,
    ) {}

    public static function fromToolField(ToolField $field): self
    {
        return new self(
            surface: QuestionAssist::SURFACE_TOOL,
            questionKey: $field->key,
            /*
             * مفتاح الحقيقة هو مفتاح الحقل نفسه: `ProjectAnswerMemory` يكتب في
             * الدماغ بـ`$key` لا بـ`profile_key` (الثاني موضعه في ملف المشروع
             * فقط). ربط الكفاية بـ`profile_key` كان سيكتب درجةً تحت مفتاح لا
             * يقرؤه حساب المحور — أي قياس صحيح لا يصل إلى شيء.
             */
            fieldKey: $field->key,
            text: $field->label,
            help: $field->help,
            why: $field->why,
            type: $field->type,
            options: array_map(
                fn (array $option) => ['value' => $option['value'] ?? '', 'label' => (string) ($option['label'] ?? $option['value'] ?? '')],
                $field->options ?? [],
            ),
            required: (bool) $field->required,
        );
    }

    /**
     * @param  array<int, array{value: mixed, label: string}>  $options
     */
    public static function fromConsultationQuestion(
        string $questionKey,
        string $fieldKey,
        string $text,
        ?string $help,
        ?string $why,
        string $type,
        array $options,
        bool $required,
    ): self {
        return new self(
            surface: QuestionAssist::SURFACE_CONSULTATION,
            questionKey: $questionKey,
            fieldKey: $fieldKey,
            text: $text,
            help: $help,
            why: $why,
            type: $type,
            options: $options,
            required: $required,
        );
    }

    /**
     * هل هذا سؤال اختيار محدود؟
     *
     * الفرق يحكم شكل المساعدة كلها: في الاختيار يُرشَّح **أفضل خيار متاح** ولا
     * يُخترع خيار جديد — قيم الخيارات مربوطة بخرائط نقاط في حساب المحاور، وقيمة
     * مُختلقة تعطي صفرًا صامتًا لمن اختارها. وفي المفتوح تُقترح صياغة ملموسة
     * يعدّلها صاحبها.
     */
    public function isChoice(): bool
    {
        return in_array($this->type, ['select', 'radio', 'boolean', 'confirmation', 'multiselect'], true);
    }

    public function isMeasurable(): bool
    {
        return AnswerFitnessScorer::measures($this->type);
    }

    public function expectation(): FieldExpectation
    {
        return FieldExpectation::for($this->fieldKey);
    }

    /**
     * بصمة السؤال نفسه — جزء من بصمة السياق. تعديل نصّ السؤال أو خياراته يُبطل
     * دليلًا كُتب عن صيغة سابقة.
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            $this->surface,
            $this->questionKey,
            $this->text,
            $this->type,
            implode(',', array_map(fn (array $option) => (string) $option['value'], $this->options)),
        ]));
    }
}
