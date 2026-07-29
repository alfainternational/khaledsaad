<?php

namespace App\Modules\Measurement\Exceptions;

use RuntimeException;

/**
 * الرفض عند بلوغ السقف (§٤.٤: توقف تلقائي عند ١٠٠٪).
 *
 * استثناء لا قيمة راجعة `false`: الحجز الفاشل يجب أن يوقف المسار كله، وقيمة
 * منطقية يمكن تجاهلها بسطر واحد فيمضي الاستدعاء بلا حجز — وهو بالضبط ما
 * يمنعه هذا السقف.
 */
class BudgetExhausted extends RuntimeException
{
    public function __construct(
        public readonly int $limit,
        public readonly int $committed,
        public readonly int $requested,
    ) {
        parent::__construct(sprintf(
            'بلغت مساحتك سقف استعلامات هذا الشهر (%d من %d). الطلب يحتاج %d استعلامًا إضافيًّا، '
            .'وسيتجدد السقف مع بداية الشهر القادم.',
            $committed,
            $limit,
            $requested,
        ));
    }
}
