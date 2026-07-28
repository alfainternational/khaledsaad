<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بوابة الحد الأدنى لإصدار التطبيق.
 *
 * السبب: لا إصدار ثانٍ للواجهة (§١٤)، فيتطوّر `api/v1` في مكانه. أي تغيير في
 * عقده يصل إلى نسخ مثبَّتة على أجهزة المستخدمين، وبلا بوابة يظهر العطل عندهم
 * كسلوك عشوائي — شاشة فارغة أو خطأ تحليل — لا كرسالة مفهومة.
 *
 * الترتيب الملزم:
 *   ١. تُشحن هذه البوابة و`min_supported_build = 0`، فتمرّ كل الطلبات.
 *   ٢. تُشحن نسخة التطبيق التي ترسل `X-App-Build`.
 *   ٣. عندها فقط يُرفع الحد ويتغيّر العقد.
 *
 * قلب الترتيب يمنع الوصول عن كل مستخدم حالي دفعة واحدة، لأن النسخة المثبَّتة
 * لا ترسل الترويسة أصلًا.
 */
class EnsureSupportedAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $minimum = (int) config('mobile.min_supported_build', 0);

        // الحد صفر يعني بوابة مشحونة غير مفعّلة — لا فحص ولا تكلفة.
        if ($minimum <= 0) {
            return $next($request);
        }

        $header = $request->header('X-App-Build');

        /*
         * غياب الترويسة يعني عميلًا سابقًا لها، وهو بالضبط ما نحرس منه.
         * معاملته كإصدار صفر مقصودة: لو مرّرناه لكان الحدّ بلا أثر على من
         * وُضع من أجله.
         */
        $build = $header === null ? 0 : (int) $header;

        if ($build >= $minimum) {
            return $next($request);
        }

        return response()->json([
            'message' => 'هذا الإصدار من التطبيق لم يعد مدعومًا. حدّثه للمتابعة.',
            'error' => 'app_update_required',
            'meta' => [
                'min_supported_build' => $minimum,
                'your_build' => $build,
                'download_url' => route('mobile.download'),
            ],
        ], Response::HTTP_UPGRADE_REQUIRED);
    }
}
