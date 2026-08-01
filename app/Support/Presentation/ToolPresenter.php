<?php

namespace App\Support\Presentation;

use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolField;
use App\Models\ToolVersion;
use App\Modules\Shared\Sectors\Sector;
use App\Services\Tools\AdLibraries;
use App\Services\Tools\MarketBenchmarks;

/**
 * مصدر واحد لشكل الأداة.
 *
 * الويب يعرض هذه المصفوفة عبر Blade، وتطبيق Flutter يستهلكها كـJSON.
 * أي حقل جديد يظهر في الاثنين معًا — وهذا هو ما يجعل النسختين متطابقتين
 * بحكم البنية لا بحكم الانضباط.
 */
class ToolPresenter
{
    public function __construct(
        private readonly MarketBenchmarks $benchmarks,
        private readonly AdLibraries $adLibraries,
    ) {}

    private ?Project $context = null;

    /**
     * سياق المشروع يجعل أرقام المقارنة أقرب لنشاط المستخدم.
     */
    public function withProject(?Project $project): self
    {
        $this->context = $project;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function card(Tool $tool): array
    {
        return [
            'key' => $tool->key,
            'name' => $tool->name,
            'title' => $tool->title,
            'description' => $tool->description,
            // لغة العميل: مشكلته، وما سيخرج به، ولمن هذا، وكم يستغرق.
            'pain' => $tool->pain,
            'promise' => $tool->promise,
            'audience' => $tool->audience,
            'duration_minutes' => $tool->duration_minutes,
            'category' => $tool->category,
            'status' => $tool->status,
            'is_runnable' => $tool->isRunnable(),
            'status_label' => $tool->isRunnable() ? 'جاهزة' : 'نعمل عليها',
            'credit_cost' => $tool->currentVersion?->credit_cost,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Tool $tool): array
    {
        $version = $tool->currentVersion;

        return [
            ...$this->card($tool),
            'version' => $version?->version,
            'step_count' => $version?->stepCount() ?? 0,
            'steps' => $version ? $this->steps($version, []) : [],
            'outputs' => $version
                ? collect($version->section_plan)->pluck('title')->all()
                : [],
            'inputs' => $version
                ? $version->fields->where('required', true)->pluck('label')->values()->all()
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<int, array<string, mixed>>
     */
    public function steps(ToolVersion $version, array $answers, array $knownKeys = []): array
    {
        $visible = $version->fields->filter(fn (ToolField $field) => $field->isVisible($answers));

        /*
         * step هو الرقم الحقيقي المخزَّن (المفتاح الذي يحفظ به الخادم)،
         * وposition هو ترتيب العرض بعد إخفاء ما لا يخص هذا المشروع.
         * الفصل بينهما ضروري: المستخدم يرى «2 من 3» بينما يحفظ الخادم
         * الخطوة رقم 4 من تعريف الأداة.
         */
        return $visible
            ->sortBy(fn (ToolField $field) => [$field->step, $field->sort_order])
            ->groupBy('step')
            ->values()
            ->map(fn ($fields, $index) => [
                'step' => (int) $fields->first()->step,
                'position' => $index + 1,
                'title' => $fields->firstWhere('step_title', '!=', null)?->step_title ?? 'الخطوة '.($index + 1),
                'fields' => $fields->map(fn (ToolField $field) => $this->field($field, $answers, $knownKeys))->values()->all(),
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function field(ToolField $field, array $answers, array $knownKeys = []): array
    {
        $value = $this->coerceValue($answers[$field->key] ?? null, $field->type);

        /*
         * مفتاح الحقل قد يتكرر بين أداتين بمعنى مختلف تمامًا: «active_channels»
         * في أداة قائمة قنوات، وفي أخرى سؤال عن عددها بخيارات أخرى. القيمة
         * المستعارة حينها لا تنتمي لخيارات هذا الحقل، فتظهر فارغة ومطلوبة —
         * وإن كانت مطوية في صندوق «نعرفها من قبل» منع المتصفح الإرسال بصمت.
         * لذلك: ما لا يصلح لهذا الحقل لا يُعدّ معروفًا، ويُسأل عنه ظاهرًا.
         */
        $value = $this->rejectValueOutsideOptions($field, $value);
        $isKnown = in_array($field->key, $knownKeys, true) && ! $this->isBlank($value);

        return [
            'key' => $field->key,
            'label' => $field->label,
            'help' => $field->help ?: $this->answerGuidance($field->type),
            // لماذا نسأل: يظهر بجانب كل سؤال حتى لا يشعر المستخدم أنه يملأ استمارة.
            'why' => $field->why,
            'example' => $field->example,
            'type' => $field->type,
            'required' => $field->required,
            'options' => $field->options ?? [],
            'value' => $value,
            // معروف مسبقًا: أجاب عنه المستخدم في مكان آخر، فلا نطلبه من جديد.
            'is_known' => $isKnown,
            // رقم للمقارنة بجانب الخانة الفارغة بدل تركه يخمّن وحده.
            'benchmark' => $this->benchmarks->forField($field->key, $this->context),
            // رؤية المنافسين: أين يرى إعلاناتهم على كل منصة اختارها أو يستطيع اختيارها.
            'competitor_view' => $this->competitorView($field),

            /*
             * القطاع الذي استدعى هذا السؤال، أو null إن كان عامًّا.
             *
             * وعدناه عند اختيار قطاعه بأن اختياره «يفتح أسئلة وفحوصات خاصة
             * بقطاعك»، ثم كانت الأسئلة القطاعية تظهر مختلطةً بالعامة بلا أي
             * وسم — فالوعد يُنفَّذ ولا يُرى. الوسم هو ما يحوّل التخصص من حقيقة
             * في المحرّك إلى تجربة عند المستخدم.
             */
            'sector' => $this->sectorOf($field),
        ];
    }

    /**
     * القطاع المشروط في `visible_when`، إن كان الشرط قطاعيًّا.
     *
     * نقرأ الشرط نفسه لا نستنتج: الحقل الذي ظهر بشرط `project.sector` ظهر
     * لأجل هذا القطاع تحديدًا، وأي استنتاج آخر تخمين.
     */
    private function sectorOf(ToolField $field): ?string
    {
        $declared = $field->visible_when['project.sector'] ?? null;
        $sector = is_array($declared) ? ($declared[0] ?? null) : $declared;

        return Sector::isSpecialized($sector) ? $sector : null;
    }

    /**
     * الإجابة المحفوظة قد تأتي من أداة أخرى بنوع مختلف (مصفوفة من multiselect
     * لحقل صار select مثلًا) — نطابقها مع نوع الحقل الحالي كي لا يسقط العرض.
     */
    private function coerceValue(mixed $value, string $type): mixed
    {
        if ($type === 'multiselect') {
            return array_values(array_filter((array) ($value ?? []), is_scalar(...)));
        }

        if (is_array($value)) {
            $scalars = array_values(array_filter($value, is_scalar(...)));

            if ($scalars === []) {
                return null;
            }

            // select/number يقبلان قيمة واحدة؛ النصوص تُضم في سطر واحد.
            return in_array($type, ['select', 'number'], true)
                ? $scalars[0]
                : implode('، ', $scalars);
        }

        return $value;
    }

    /**
     * يُسقط القيمة التي لا تنتمي لخيارات الحقل (لحقول الاختيار فقط).
     */
    private function rejectValueOutsideOptions(ToolField $field, mixed $value): mixed
    {
        if (! in_array($field->type, ['select', 'multiselect'], true) || $this->isBlank($value)) {
            return $value;
        }

        $allowed = collect($field->options ?? [])->pluck('value')->map(fn ($v) => (string) $v);

        if ($allowed->isEmpty()) {
            return $value;
        }

        if ($field->type === 'multiselect') {
            // نُبقي ما يصلح فقط بدل إسقاط الاختيار كله.
            return array_values(array_filter(
                (array) $value,
                fn ($item) => $allowed->contains((string) $item),
            ));
        }

        return $allowed->contains((string) $value) ? $value : null;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function answerGuidance(string $type): string
    {
        return match ($type) {
            'select' => 'اختر الإجابة الأقرب إلى وضع مشروعك الآن.',
            'multiselect' => 'اختر كل ما ينطبق على وضع مشروعك الآن.',
            'number' => 'اكتب الرقم التقريبي إذا لم يكن لديك رقم دقيق.',
            'url' => 'الصق الرابط كاملًا، مثل: https://example.com',
            default => 'اكتب إجابتك بطريقتك، ويمكنك استخدام مثال من واقع مشروعك.',
        };
    }

    /**
     * لأسئلة المنصات الإعلانية: نافذة على إعلانات المنافسين على كل منصة معروضة.
     *
     * @return array<int, array<string, mixed>>
     */
    private function competitorView(ToolField $field): array
    {
        if ($field->key !== 'ad_platforms') {
            return [];
        }

        $values = collect($field->options ?? [])->pluck('value')->all();

        return $this->adLibraries->forPlatforms($values);
    }
}
