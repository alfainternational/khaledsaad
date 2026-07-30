<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * حدّ مالي أوقف تشغيلًا قبل أي استدعاء مدفوع: رصيد غير كافٍ أو حصة خطة مستنفدة.
 *
 * تصنيفه صريح (`kind`) كي تعرف الواجهة أن إعادة المحاولة لن تُجدي شيئًا — فالسبب
 * ليس عطلًا عابرًا بل حدّ يُرفع من صفحة الفوترة. يرث `RuntimeException` كي يظل
 * ما يلتقطه اليوم يلتقطه، ويضيف فقط إشارةً يمكن للطبقات العليا التمييز بها.
 */
class BillingLimitException extends RuntimeException
{
    public const KIND_CREDITS = 'credits';

    public const KIND_QUOTA = 'quota';

    public function __construct(
        public readonly string $kind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function credits(int $needed, int $have): self
    {
        return new self(
            self::KIND_CREDITS,
            "رصيدك غير كافٍ لتشغيل هذه الأداة. تحتاج {$needed} رصيدًا ولديك {$have}.",
        );
    }

    public static function quota(?int $limit): self
    {
        return new self(
            self::KIND_QUOTA,
            $limit === 0
                ? 'خطتك الحالية لا تسمح بتشغيل الأدوات. فعّل خطة مناسبة من صفحة الفوترة.'
                : "استهلكت حصة خطتك لهذا الشهر ({$limit} تشغيل). ارفع خطتك أو انتظر بداية الشهر القادم.",
        );
    }
}
