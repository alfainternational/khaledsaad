<?php

namespace App\Modules\Reporting\Templates;

final class ResolvedTemplate
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @param  array<int, string>  $tips
     * @param  array<int, array{key: string, label: string, help: ?string, source: string}>  $gaps
     */
    public function __construct(
        public readonly int $templateId,
        public readonly string $objectiveId,
        public readonly string $kind,
        public readonly string $title,
        public readonly array $blocks,
        public readonly array $tips,
        public readonly bool $isHypothesis,
        public readonly int $version,
        public readonly string $locale = 'ar',
        /**
         * الحقول التي لم يُجب عنها صاحب النشاط بعد، فبقيت كتلها فارغة.
         *
         * ليست سببًا لحجب الورقة: الورقة تخرج ناقصةً معلنة النقص، ومعها
         * مفاتيح تفتح شاشة الإجابة. الحجب كان يترك صاحبها بلا شيء وبلا
         * طريق — وهو ما جعل «ناقص نعرفه عنك» جملةً بلا باب.
         */
        public readonly array $gaps = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->templateId,
            'objective_id' => $this->objectiveId,
            'kind' => $this->kind,
            'title' => $this->title,
            'blocks' => $this->blocks,
            'tips' => $this->tips,
            'is_hypothesis' => $this->isHypothesis,
            'version' => $this->version,
            'locale' => $this->locale,
            'gaps' => $this->gaps,
        ];
    }
}
