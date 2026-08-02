<?php

namespace App\Modules\Diagnosis;

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
     * @return array{score: int, band: string, total_weight: float, breakdown: array<int, array<string, mixed>>, excluded: array<int, array<string, mixed>>}
     */
    public function score(ToolVersion $version, array $answers, ?array $activeKeys = null): array
    {
        $rules = $version->scoring_rules['rules'] ?? [];
        $excluded = [];

        if ($activeKeys !== null) {
            foreach ($rules as $rule) {
                if (! in_array($rule['field'] ?? '', $activeKeys, true)) {
                    $excluded[] = [
                        'field' => $rule['field'] ?? '',
                        'label' => $rule['label'] ?? ($rule['field'] ?? ''),
                        'weight' => (float) ($rule['weight'] ?? 1),
                    ];
                }
            }

            $rules = array_values(array_filter(
                $rules,
                fn (array $rule) => in_array($rule['field'] ?? '', $activeKeys, true),
            ));
        }

        if ($rules === []) {
            return ['score' => 0, 'band' => 'غير محسوبة', 'total_weight' => 0.0, 'breakdown' => [], 'excluded' => $excluded];
        }

        $totalWeight = 0.0;
        $earned = 0.0;
        $rows = [];

        foreach ($rules as $rule) {
            $weight = (float) ($rule['weight'] ?? 1);
            $value = $answers[$rule['field']] ?? null;
            $factor = $this->factor($rule, $value);

            $totalWeight += $weight;
            $earned += $weight * $factor;

            $rows[] = [
                'field' => $rule['field'],
                'label' => $rule['label'] ?? $rule['field'],
                'weight' => $weight,
                'factor' => round($factor, 2),
                'points' => round($weight * $factor, 2),
                // الخام يُحفظ ليُترجَم لاحقًا إلى نص الخيار الذي اختاره المستخدم.
                'value' => is_array($value) ? $value : (string) ($value ?? ''),
                'rule_type' => $rule['type'] ?? 'present',
                'scale' => $this->scale($rule),
            ];
        }

        // الحصة تُحسب هنا لا في العرض: البند وزنه ثابت لكن نصيبه من الدرجة
        // يتغير بتغير القواعد المفعّلة، وإخفاء ذلك يجعل «10 / 10» رقمًا بلا معنى.
        foreach ($rows as $index => $row) {
            $rows[$index]['share'] = $totalWeight > 0
                ? round($row['weight'] / $totalWeight * 100, 1)
                : 0.0;
        }

        $score = $totalWeight > 0 ? (int) round($earned / $totalWeight * 100) : 0;

        return [
            'score' => max(0, min(100, $score)),
            'band' => Report::bandFor($score),
            'total_weight' => $totalWeight,
            'breakdown' => $rows,
            'excluded' => $excluded,
        ];
    }

    /**
     * سلّم التقدير المعلن: أي إجابة تعطي أي معامل. من لا يرى السلّم لا يعرف
     * كم كان يفصله عن الدرجة الكاملة ولا ما الإجابة التي كانت ترفعه.
     *
     * @param  array<string, mixed>  $rule
     * @return array<int, array{key: string, factor: float}>
     */
    private function scale(array $rule): array
    {
        if (($rule['type'] ?? 'present') !== 'map' || ! is_array($rule['map'] ?? null)) {
            return [];
        }

        $scale = [];

        foreach ($rule['map'] as $key => $factor) {
            $scale[] = ['key' => (string) $key, 'factor' => (float) $factor];
        }

        return $scale;
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
