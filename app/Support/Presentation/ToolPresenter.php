<?php

namespace App\Support\Presentation;

use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolField;
use App\Models\ToolVersion;
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

        return $visible
            ->groupBy('step')
            ->map(fn ($fields, $step) => [
                'step' => (int) $step,
                'title' => $fields->firstWhere('step_title', '!=', null)?->step_title ?? "الخطوة {$step}",
                'fields' => $fields->map(fn (ToolField $field) => $this->field($field, $answers, $knownKeys))->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public function field(ToolField $field, array $answers, array $knownKeys = []): array
    {
        return [
            'key' => $field->key,
            'label' => $field->label,
            'help' => $field->help,
            // لماذا نسأل: يظهر بجانب كل سؤال حتى لا يشعر المستخدم أنه يملأ استمارة.
            'why' => $field->why,
            'example' => $field->example,
            'type' => $field->type,
            'required' => $field->required,
            'options' => $field->options ?? [],
            'value' => $answers[$field->key] ?? ($field->type === 'multiselect' ? [] : null),
            // معروف مسبقًا: أجاب عنه المستخدم في مكان آخر، فلا نطلبه من جديد.
            'is_known' => in_array($field->key, $knownKeys, true),
            // رقم للمقارنة بجانب الخانة الفارغة بدل تركه يخمّن وحده.
            'benchmark' => $this->benchmarks->forField($field->key, $this->context),
            // رؤية المنافسين: أين يرى إعلاناتهم على كل منصة اختارها أو يستطيع اختيارها.
            'competitor_view' => $this->competitorView($field),
        ];
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
