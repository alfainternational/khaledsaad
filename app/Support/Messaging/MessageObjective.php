<?php

namespace App\Support\Messaging;

/**
 * هدف الرسالة الواحد.
 *
 * رسالة بهدفين لا تحقق أيًّا منهما: من يبني الثقة لا يطلب الشراء في
 * الجملة نفسها. الهدف يُختار قبل الكتابة ويُقاس عليه الرد.
 */
enum MessageObjective: string
{
    case Attention = 'attention';
    case Trust = 'trust';
    case Objection = 'objection';
    case Action = 'action';

    public function label(): string
    {
        return match ($this) {
            self::Attention => __('جذب الانتباه'),
            self::Trust => __('بناء الثقة'),
            self::Objection => __('معالجة اعتراض'),
            self::Action => __('دفع إلى إجراء'),
        };
    }

    public function instruction(): string
    {
        return match ($this) {
            self::Attention => __('أوقف التمرير بجملة تخص وجعها هي — لا تطلب شراءً بعد.'),
            self::Trust => __('قدّم دليلًا ملموسًا أو تجربة مشابهة لحالتها، ولا تعِد بما لا يُثبت.'),
            self::Objection => __('واجه اعتراضها المحدد صراحةً وأجب عنه في الرسالة نفسها.'),
            self::Action => __('اطلب إجراءً واحدًا واضحًا بلا خيارات متعددة.'),
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            [],
        );
    }
}
