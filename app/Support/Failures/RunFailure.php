<?php

declare(strict_types=1);

namespace App\Support\Failures;

/**
 * العطل كما يراه المستخدم — لا كما وقع في الكود.
 *
 * الحقول الأربعة تجيب على الأسئلة الأربعة الإلزامية لشاشة الفشل:
 * ماذا حدث (`title`)، ولماذا وماذا كلّفني (`message`)، وماذا أفعل الآن
 * (`userAction`). رسالة الاستثناء الأصلية لا تدخل هنا أبدًا — مكانها
 * السجلّ ومرحلة التشغيل، حيث يقرأها من يستطيع التصرف بها.
 */
final class RunFailure
{
    public function __construct(
        public readonly FailureKind $kind,
        public readonly string $code,
        public readonly string $title,
        public readonly string $message,
        public readonly ?RunFailureAction $userAction = null,
        public readonly ?int $retryAfter = null,
    ) {
        // الحارس في البناء لا في المراجعة: عطلٌ لدينا يحمل إجراءً للمستخدم
        // يعني أننا حمّلناه ما ليس عليه، وهو بالضبط ما وقع في التدقيق.
        if (! $kind->allowsUserAction() && $userAction !== null) {
            throw new \LogicException(
                "عطل من نوع {$kind->value} لا يجوز أن يحمل إجراءً مطلوبًا من المستخدم (INV-8).",
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind->value,
            'code' => $this->code,
            'title' => $this->title,
            'message' => $this->message,
            'user_action' => $this->userAction?->toArray(),
            'retry_after' => $this->retryAfter,
        ];
    }
}
