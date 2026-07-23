<?php

namespace App\Services\Tools;

use App\Models\ToolField;
use App\Models\ToolRun;
use App\Models\ToolVersion;
use Illuminate\Support\Collection;

/**
 * اكتمال الإجابات يُحسب على الحقول المرئية فقط: حقل مخفي بشرط
 * لا يجوز أن يمنع التشغيل.
 *
 * الرؤية تُقيَّم على الإجابات مدموجة بسياق المشروع (النوع والحالة)،
 * فالأسئلة تتكيف مع كل مشروع دون أن يختل حساب الاكتمال أو الدرجة.
 */
class AnswerCompleteness
{
    public function __construct(private readonly ProjectContextResolver $context) {}

    /**
     * @return array<int, string> عناوين الحقول الناقصة.
     */
    public function missingRequired(ToolRun $run): array
    {
        $answers = $this->contextualAnswers($run);

        return $this->visibleFields($run->toolVersion, $answers)
            ->filter(fn ($field) => $field->required && $this->isEmpty($answers[$field->key] ?? null))
            ->pluck('label')
            ->values()
            ->all();
    }

    public function percent(ToolRun $run): int
    {
        $answers = $this->contextualAnswers($run);
        $fields = $this->visibleFields($run->toolVersion, $answers)->where('required', true);

        if ($fields->isEmpty()) {
            return 100;
        }

        $filled = $fields->filter(fn ($field) => ! $this->isEmpty($answers[$field->key] ?? null))->count();

        return (int) round($filled / $fields->count() * 100);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return Collection<int, ToolField>
     */
    public function visibleFields(ToolVersion $version, array $answers): Collection
    {
        return $version->fields->filter(fn ($field) => $field->isVisible($answers))->values();
    }

    /**
     * الإجابات + مفاتيح سياق المشروع المحجوزة (project.*) — الصيغة الموحدة
     * التي تُقيَّم عليها كل شروط الرؤية.
     *
     * @return array<string, mixed>
     */
    public function contextualAnswers(ToolRun $run): array
    {
        return array_merge(
            $this->plainAnswers($run),
            $this->context->for($run->project),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function plainAnswers(ToolRun $run): array
    {
        return collect($run->answerMap())
            ->map(fn ($value) => is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value)
            ->all();
    }

    private function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return $value === [];
        }

        return $value === null || trim((string) $value) === '';
    }
}
