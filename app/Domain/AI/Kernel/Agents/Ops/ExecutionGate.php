<?php

namespace App\Domain\AI\Kernel\Agents\Ops;

/**
 * بوابة التنفيذ — نواة وكيل execution-coordinator محلياً.
 *
 * آخر خط قبل النشر الخارجي: تفرض موافقة بشرية إلزامية، وتتحقّق من سقف الميزانية
 * ووجود محتوى، وتعيد قائمة الموانع. لا تنفّذ أبداً تلقائياً — القرار النهائي بشري.
 * نقي بلا مورد خارجي. (قدرة hidden — flag execution.publish.)
 */
class ExecutionGate
{
    /**
     * @param  array{budget?: int|float, spent?: int|float}  $context
     * @return array{approval_required: bool, content_present: bool, budget_ok: bool|null, can_execute: bool, blockers: array<int, string>}
     */
    public function assess(string $content, array $context = []): array
    {
        $blockers = [];

        $contentPresent = trim($content) !== '';
        if (! $contentPresent) {
            $blockers[] = 'لا يوجد محتوى للنشر.';
        }

        $budgetOk = null;
        if (isset($context['budget'], $context['spent'])) {
            $budgetOk = (float) $context['spent'] <= (float) $context['budget'];
            if (! $budgetOk) {
                $blockers[] = 'تجاوز سقف الميزانية المسموح.';
            }
        }

        // الموافقة البشرية إلزامية دائماً — لا تنفيذ تلقائي.
        $blockers[] = 'بانتظار موافقة بشرية صريحة.';

        return [
            'approval_required' => true,
            'content_present' => $contentPresent,
            'budget_ok' => $budgetOk,
            'can_execute' => false, // لا يُنفَّذ إلا بعد موافقة صريحة خارج هذه البوابة.
            'blockers' => $blockers,
        ];
    }
}
