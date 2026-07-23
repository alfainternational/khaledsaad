<?php

namespace App\Services\Tools;

use App\Models\ToolField;
use App\Models\ToolRun;
use App\Models\ToolVersion;
use Illuminate\Support\Collection;

/**
 * اكتمال الإجابات يُحسب على الحقول المرئية فقط: حقل مخفي بشرط
 * لا يجوز أن يمنع التشغيل.
 */
class AnswerCompleteness
{
    /**
     * @return array<int, string> عناوين الحقول الناقصة.
     */
    public function missingRequired(ToolRun $run): array
    {
        $answers = $this->plainAnswers($run);

        return $this->visibleFields($run->toolVersion, $answers)
            ->filter(fn ($field) => $field->required && $this->isEmpty($answers[$field->key] ?? null))
            ->pluck('label')
            ->values()
            ->all();
    }

    public function percent(ToolRun $run): int
    {
        $answers = $this->plainAnswers($run);
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
