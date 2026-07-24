<?php

namespace App\Http\Controllers\Concerns;

/**
 * التنقل بين خطوات المعالج بأرقام الخطوات الحقيقية لا بترتيبها.
 *
 * سبب وجوده: الأسئلة تتكيف مع نوع المشروع وحالته، فقد تختفي خطوة كاملة
 * لمشروع لا تعنيه. الاعتماد على الترتيب كان يجعل الرابط يشير إلى خطوة
 * والحفظ يبحث عن أخرى، فينكسر المعالج بلا سبب ظاهر للمستخدم.
 */
trait NavigatesWizardSteps
{
    /**
     * @param  array<int, array<string, mixed>>  $steps
     * @return array<string, mixed>|null
     */
    protected function currentStep(array $steps, int $step): ?array
    {
        foreach ($steps as $entry) {
            if ((int) $entry['step'] === $step) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    protected function stepAfter(array $steps, int $step): ?int
    {
        foreach ($steps as $entry) {
            if ((int) $entry['step'] > $step) {
                return (int) $entry['step'];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    protected function stepBefore(array $steps, int $step): ?int
    {
        $previous = null;

        foreach ($steps as $entry) {
            if ((int) $entry['step'] >= $step) {
                break;
            }

            $previous = (int) $entry['step'];
        }

        return $previous;
    }

    /**
     * أقرب خطوة قائمة لرقم لم يعد موجودًا — يمنع الوقوف أمام صفحة مفقودة.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    protected function nearestStep(array $steps, int $step): ?int
    {
        if ($steps === []) {
            return null;
        }

        return $this->stepAfter($steps, $step - 1) ?? (int) $steps[array_key_last($steps)]['step'];
    }
}
