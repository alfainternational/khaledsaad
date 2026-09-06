<?php

namespace App\Exceptions;

use App\Support\Presentation\Num;
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

    /**
     * الرسالة تمرّ بالتمييز العربي لا بالسَّلسَلة: «تحتاج 5 رصيدًا» كانت
     * تُطبع كما هي في كل شاشة تصطدم بالحدّ.
     */
    public static function credits(int $needed, int $have): self
    {
        return new self(
            self::KIND_CREDITS,
            // الصيغة من §10: التكلفة أولًا ثم الرصيد، بلا حكمٍ على المستخدم.
            // «رصيدك غير كافٍ» تخبره بما ينقصه ولا تخبره بكم — والرقمان
            // معًا هما ما يمكّنه من قرار الشحن.
            __('تشغيل هذه الأداة يكلّف :needed، ورصيدك الحالي :have.', [
                'needed' => Num::credits($needed),
                'have' => Num::int($have),
            ]),
        );
    }

    public static function quota(?int $limit): self
    {
        return new self(
            self::KIND_QUOTA,
            $limit === 0
                ? __('خطتك الحالية لا تسمح بتشغيل الأدوات. فعّل خطة مناسبة من صفحة الفوترة.')
                : trans_choice(
                    '{1} استهلكت حصة خطتك لهذا الشهر (تشغيل واحد). ارفع خطتك أو انتظر بداية الشهر القادم.'
                    .'|{2} استهلكت حصة خطتك لهذا الشهر (تشغيلان). ارفع خطتك أو انتظر بداية الشهر القادم.'
                    .'|[3,10] استهلكت حصة خطتك لهذا الشهر (:count تشغيلات). ارفع خطتك أو انتظر بداية الشهر القادم.'
                    .'|[11,*] استهلكت حصة خطتك لهذا الشهر (:count تشغيلًا). ارفع خطتك أو انتظر بداية الشهر القادم.',
                    (int) $limit,
                    ['count' => Num::int((int) $limit)],
                ),
        );
    }
}
