<?php

namespace App\Services\Tools;

use App\Models\Report;
use App\Models\ToolVersion;

/**
 * الدرجة الأساسية تُحسب بقواعد حتمية لا بذكاء اصطناعي.
 *
 * السبب: الدرجة تُعرض قبل التسجيل وقبل أي استدعاء مدفوع، ويجب أن تكون
 * ثابتة وقابلة للتكرار وقابلة للشرح. لو حسبها نموذج لتغيرت بين تشغيلين
 * بنفس الإجابات، وانهارت قيمة المقارنة الزمنية بالكامل.
 */
class DeterministicScorer
{
    /**
     * @param  array<string, mixed>  $answers
     * @param  array<int, string>|null  $activeKeys  مفاتيح الحقول المنطبقة على
     *                                               هذا المشروع (بحسب نوعه وحالته). null = كل القواعد كما كانت.
     *                                               القاعدة التي يخفي سياقُ المشروع سؤالَها تُستبعد من الوزن كليًا:
     *                                               مشروع فكرة لا يُعاقَب على سؤال قنوات لم يُوجَّه له أصلًا.
     * @return array{score: int, band: string, breakdown: array<int, array<string, mixed>>}
     */
    public function score(ToolVersion $version, array $answers, ?array $activeKeys = null): array
    {
        $rules = $version->scoring_rules['rules'] ?? [];

        if ($activeKeys !== null) {
            $rules = array_values(array_filter(
                $rules,
                fn (array $rule) => in_array($rule['field'] ?? '', $activeKeys, true),
            ));
        }

        if ($rules === []) {
            return ['score' => 0, 'band' => 'غير محسوبة', 'breakdown' => []];
        }

        $totalWeight = 0.0;
        $earned = 0.0;
        $breakdown = [];

        foreach ($rules as $rule) {
            $weight = (float) ($rule['weight'] ?? 1);
            $factor = $this->factor($rule, $answers[$rule['field']] ?? null);

            $totalWeight += $weight;
            $earned += $weight * $factor;

            $breakdown[] = [
                'field' => $rule['field'],
                'label' => $rule['label'] ?? $rule['field'],
                'weight' => $weight,
                'factor' => round($factor, 2),
                'points' => round($weight * $factor, 2),
            ];
        }

        $score = $totalWeight > 0 ? (int) round($earned / $totalWeight * 100) : 0;

        return [
            'score' => max(0, min(100, $score)),
            'band' => Report::bandFor($score),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function factor(array $rule, mixed $value): float
    {
        return match ($rule['type'] ?? 'present') {
            'map' => (float) ($rule['map'][$this->key($value)] ?? 0),
            'count' => $this->countFactor($value, (int) ($rule['target'] ?? 1)),
            'range' => $this->rangeFactor($value, $rule),
            default => $this->presentFactor($value),
        };
    }

    private function key(mixed $value): string
    {
        return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
    }

    private function presentFactor(mixed $value): float
    {
        if (is_array($value)) {
            return $value === [] ? 0.0 : 1.0;
        }

        return trim((string) $value) === '' ? 0.0 : 1.0;
    }

    private function countFactor(mixed $value, int $target): float
    {
        $count = is_array($value) ? count($value) : (int) $value;

        return $target <= 0 ? 0.0 : min(1.0, $count / $target);
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function rangeFactor(mixed $value, array $rule): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        $min = (float) ($rule['min'] ?? 0);
        $max = (float) ($rule['max'] ?? 100);

        if ($max <= $min) {
            return 0.0;
        }

        return max(0.0, min(1.0, ((float) $value - $min) / ($max - $min)));
    }
}
