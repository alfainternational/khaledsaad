<?php

declare(strict_types=1);

namespace App\Support\AI\Resilience;

/**
 * صحة المزوّد كما يقرأها النظام قبل أن يرسل إليه طلبًا.
 *
 * العطل الذي تسدّه: كنّا نكتشف نفاد المزوّد بأن يفشل طلبُ مستخدم. أي أن
 * أول من يعرف أن خدمتنا متوقفة هو صاحب الستين إجابة. الحالة هنا تُقرأ
 * قبل الإرسال، فيُحوَّل الطلب إلى مزوّد آخر أو يُؤجَّل بلا أن يُحرق مجهود.
 */
enum ProviderHealth: string
{
    /** يعمل. */
    case Ok = 'ok';

    /** يعمل ببطء أو بأخطاء متفرقة — يُستعمل ولا يُوثق به وحده. */
    case Degraded = 'degraded';

    /** نفدت حصته أو اشتراكه. لا يُرسل إليه شيء حتى يُعاد شحنه. */
    case Exhausted = 'exhausted';

    /** لا يستجيب. */
    case Down = 'down';

    public function canServe(): bool
    {
        return $this === self::Ok || $this === self::Degraded;
    }

    public function label(): string
    {
        return match ($this) {
            self::Ok => __('يعمل'),
            self::Degraded => __('متذبذب'),
            self::Exhausted => __('نفدت حصته'),
            self::Down => __('متوقف'),
        };
    }
}
