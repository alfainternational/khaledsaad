<?php

namespace App\Modules\Insights;

use App\Modules\Insights\Models\VisitorSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * تسجيل التحويل من الخادم — الطبقة التي كانت مفقودة كليًّا.
 *
 * `conversion_events` في الإعداد كانت تعرّف تسعة أحداث ولا يُطلقها أحد:
 * لا سطر في المشروع كله يستدعي `ksInsights.track`، والبيكون يرسل اسمًا
 * واحدًا لكل النماذج هو `form_submit` — وهو ليس مفتاحًا في تلك القائمة.
 * فكان عمود «التحويل» صفرًا **بالبناء** لا بالنتيجة، ويبقى صفرًا ولو
 * سجّل ألف مستخدم.
 *
 * ولا ينفع إصلاحه في المتصفح: التسجيل الناجح `POST` ثم `302`، والوسيط
 * يتجاهل الاثنين عمدًا (ليس مشاهدةَ صفحة، والتحويلة محطة لا وجهة).
 * فالمكان الوحيد الذي يعرف أن الحساب أُنشئ فعلًا هو الخادم نفسه.
 *
 * الجلسة تُستخرج من كوكي الزيارة لا من جلسة Laravel: الكوكي هو ما يربط
 * الفعل بالزيارة التي جاءت من قناة معيّنة — وبدونه يُنسب التحويل إلى
 * لا شيء فيضيع «ما الذي جلب هذا المستخدم».
 */
class ConversionRecorder
{
    public function __construct(
        private readonly VisitorIdentity $identity,
        private readonly VisitRecorder $recorder,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(Request $request, string $name, ?string $label = null, array $meta = []): void
    {
        if (! config('insights.enabled', true)) {
            return;
        }

        /*
         * الاسم يجب أن يكون معرَّفًا في الإعداد.
         *
         * حدثٌ باسم غير مُدرج يُكتب صفًّا لا يُحتسب تحويلًا، فيبدو الأمر
         * ناجحًا في الكود وصفرًا في اللوحة — وهو العطل نفسه الذي نصلحه
         * هنا. الفشل الصامت أسوأ من الاستثناء، فيُسجَّل تحذيرًا.
         */
        if (! array_key_exists($name, (array) config('insights.conversion_events', []))) {
            Log::warning('[insights] اسم تحويل غير معرَّف في الإعداد', ['name' => $name]);

            return;
        }

        try {
            $uuid = $this->identity->currentVisit($request);

            if ($uuid === null) {
                return;
            }

            $session = VisitorSession::where('uuid', $uuid)->first();

            if ($session === null) {
                return;
            }

            /*
             * النموذج المُرسَل إثباتُ متصفّح كالنبضة تمامًا.
             *
             * لا يصل إلى هنا إلا طلبٌ حمل رمز CSRF من صفحة عرضناها نحن،
             * ومرّ بالتحقق كاملًا. فلو بقيت الجلسة «غير متحقَّقة» لسقط
             * التحويل من اللوحة — أي لأخفى الإصلاحُ ما جاء يُظهره.
             */
            $this->recorder->verify($session, 'form');

            $this->recorder->recordEvent($session, null, [
                'name' => $name,
                'category' => 'conversion',
                'label' => $label,
                'path' => $this->recorder->path($request),
                'meta' => $meta === [] ? null : $meta,
            ]);
        } catch (\Throwable $e) {
            // القياس لا يُسقط تسجيلًا ناجحًا على مستخدم.
            Log::warning('[insights] تعذّر تسجيل تحويل', ['name' => $name, 'error' => $e->getMessage()]);
        }
    }
}
