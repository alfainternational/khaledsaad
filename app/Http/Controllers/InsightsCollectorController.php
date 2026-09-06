<?php

namespace App\Http\Controllers;

use App\Modules\Insights\Models\VisitorPageView;
use App\Modules\Insights\Models\VisitorSession;
use App\Modules\Insights\VisitorIdentity;
use App\Modules\Insights\VisitRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * نقطة الجمع: ما لا يعرفه الخادم عن الزيارة.
 *
 * الخادم يعرف أن الصفحة طُلبت، ولا يعرف أنها قُرئت. الفرق بينهما هو
 * كل ما يصل من هنا: الزمن النشط، وعمق التمرير، والتفاعلات، ومقاس
 * الشاشة، والمنطقة الزمنية التي نستنتج منها البلد.
 *
 * الحراسة ثلاثية: المسار يقبل معرّفات موجودة فعلًا في قاعدتنا، ومحدود
 * بمعدّل، ولا يقبل إنشاء جلسة من العدم. من يرسل معرّفًا لا نعرفه يُردّ
 * بصمت — نقطة جمع تُنشئ صفوفًا بما يُملى عليها ليست قياسًا بل استمارة
 * مفتوحة لأي عابر.
 */
class InsightsCollectorController extends Controller
{
    public function __invoke(Request $request, VisitRecorder $recorder, VisitorIdentity $identity): Response
    {
        if (! config('insights.enabled', true)) {
            return $this->accepted();
        }

        $data = $this->payload($request);

        if ($data === null) {
            return $this->accepted();
        }

        $session = VisitorSession::where('uuid', $data['visit'])->first();

        if ($session === null) {
            return $this->accepted();
        }

        // البيكون نشاط: تجديد كوكي الزيارة يمنع انقسام قراءة طويلة على
        // صفحة واحدة إلى جلستين لمجرّد أن الزائر لم ينتقل خلال نصف ساعة.
        $identity->rememberVisit($session->uuid);

        /*
         * وصولُ هذا الطلب هو الإثبات نفسه.
         *
         * لا يبلغ هذا السطر إلا عميلٌ حمّل الصفحة، قرأ الوسوم المحقونة
         * فيها، ونفّذ السكربت. الترتيب مقصود: التعليم قبل تسجيل الحدث
         * حتى يُولد صفّ الحدث متحقَّقًا لا مُرقًّى بعد كتابته.
         */
        $recorder->verify($session, 'beacon');

        $view = $data['view'] !== null
            ? VisitorPageView::where('uuid', $data['view'])->where('session_id', $session->id)->first()
            : null;

        $this->enrichSession($session, $data['context']);

        match ($data['type']) {
            'event' => $recorder->recordEvent($session, $view, $data['event'] ?? []),
            default => $recorder->heartbeat(
                $session,
                $view,
                $data['active_seconds'],
                $data['scroll_percent'],
                $data['interactions'],
            ),
        };

        return $this->accepted();
    }

    /**
     * الحمولة بعد التحقق. أي شكل غير متوقّع = تجاهل صامت لا خطأ:
     * البيكون يُرسل عند إغلاق التبويب، ولا أحد يقرأ خطأً هناك.
     *
     * @return array<string, mixed>|null
     */
    private function payload(Request $request): ?array
    {
        $raw = $request->all();

        // sendBeacon يرسل Blob نصيًّا لا JSON مُعلَنًا، فقد يصل الجسم خامًا.
        if ($raw === [] && ($body = $request->getContent()) !== '') {
            $decoded = json_decode($body, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        $visit = $raw['visit'] ?? null;

        if (! is_string($visit) || ! preg_match('/^[0-9a-f-]{36}$/i', $visit)) {
            return null;
        }

        $view = $raw['view'] ?? null;

        return [
            'visit' => $visit,
            'view' => is_string($view) && preg_match('/^[0-9a-f-]{36}$/i', $view) ? $view : null,
            'type' => in_array($raw['type'] ?? '', ['heartbeat', 'exit', 'event'], true) ? $raw['type'] : 'heartbeat',

            // سقوف صارمة: بلا سقف يصير الزمن النشط حقلًا يكتبه العميل
            // بما يشاء، فيُفسد كل متوسط في اللوحة بطلب واحد ملفّق.
            'active_seconds' => min(7200, max(0, (int) ($raw['active_seconds'] ?? 0))),
            'scroll_percent' => min(100, max(0, (int) ($raw['scroll_percent'] ?? 0))),
            'interactions' => min(1000, max(0, (int) ($raw['interactions'] ?? 0))),
            'context' => is_array($raw['context'] ?? null) ? $raw['context'] : [],
            'event' => is_array($raw['event'] ?? null) ? $raw['event'] : null,
        ];
    }

    /**
     * ما يعرفه المتصفح عن نفسه: المقاس والمنطقة الزمنية.
     *
     * يُكتب مرة واحدة عند أول نبضة ولا يُعاد: الجلسة الواحدة لا يتغيّر
     * فيها بلد الزائر، وإعادة الكتابة على كل نبضة استعلامُ تحديث بلا
     * معلومة جديدة على كل خمس عشرة ثانية من كل تبويب مفتوح.
     *
     * @param  array<string, mixed>  $context
     */
    private function enrichSession(VisitorSession $session, array $context): void
    {
        if ($session->timezone !== null || $context === []) {
            return;
        }

        $timezone = is_string($context['tz'] ?? null) ? mb_substr($context['tz'], 0, 60) : null;

        $place = app(\App\Modules\Insights\LocationInference::class)
            ->resolve($timezone, $session->language);

        $session->forceFill(array_filter([
            'timezone' => $timezone,
            'screen_width' => $this->dimension($context['sw'] ?? null),
            'screen_height' => $this->dimension($context['sh'] ?? null),
            'viewport_width' => $this->dimension($context['vw'] ?? null),
            'viewport_height' => $this->dimension($context['vh'] ?? null),

            // المنطقة الزمنية أقوى إشارةً من اللغة، فتُرقّي الاستنتاج
            // السابق المبنيّ على اللغة وحدها — ويبقى المستوى `inferred`.
            'country' => $place['country'],
            'location_basis' => $place['basis'],
        ], static fn ($value) => $value !== null))->save();
    }

    private function dimension(mixed $value): ?int
    {
        $number = (int) $value;

        return $number > 0 && $number <= 20000 ? $number : null;
    }

    /**
     * ٢٠٤ دائمًا وبلا جسم: `sendBeacon` لا يقرأ ردًّا، وأي جسم هنا
     * حِمل شبكي على كل نبضة بلا قارئ.
     */
    private function accepted(): Response
    {
        return response()->noContent();
    }
}
