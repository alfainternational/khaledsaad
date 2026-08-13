<?php

namespace App\Http\Middleware;

use App\Modules\Insights\VisitorIdentity;
use App\Modules\Insights\VisitRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * التقاط الزيارة من الخادم — الطبقة التي لا يحجبها شيء.
 *
 * السكربتات وحدها لا تكفي: مانع الإعلانات يحجبها، وزائر بلا جافاسكربت
 * يختفي منها، والبوت لا ينفّذها أصلًا. ما يصل إلى هنا هو كل طلب فعلًا،
 * فيصير هذا هو **العدّ الحقيقي**، ويبقى البيكون طبقةً تضيف ما لا يعرفه
 * الخادم: كم بقي، وإلى أين مرّر، وبماذا تفاعل.
 *
 * كل الكتابة في `terminate` بعد إرسال الاستجابة: القياس يجب ألّا يضيف
 * مللي ثانية واحدة إلى انتظار الزائر.
 */
class TrackVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldTrack($request)) {
            return $next($request);
        }

        $identity = app(VisitorIdentity::class);
        $visitor = $identity->resolve($request);

        /*
         * انتهاء الجلسة يُدار بعمر الكوكي لا باستعلام.
         *
         * كوكي الزيارة عمره نافذة الجلسة ويُجدَّد مع كل طلب، فالخمول
         * ثلاثين دقيقة يُفقده تلقائيًّا ويبدأ الطلب التالي زيارة جديدة —
         * بلا استعلام تحقّق على كل صفحة.
         */
        $visitUuid = $identity->currentVisit($request) ?? (string) Str::uuid();

        $identity->remember($visitor['id']);
        $identity->rememberVisit($visitUuid);

        // معرّف المشاهدة يُولَّد قبل العرض ليُحقن في الصفحة، فيعرف البيكون
        // أيّ صفحة يُحدّث. توليده بعدها يجعل الصفحة تنبض بلا هوية.
        $request->attributes->set('insights', [
            'visit' => $visitUuid,
            'visitor' => $visitor['id'],
            'new_visitor' => $visitor['is_new'],
            'view' => (string) Str::uuid(),
            'started' => microtime(true),
        ]);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $context = $request->attributes->get('insights');

        if (! is_array($context) || ! $this->shouldRecord($response)) {
            return;
        }

        /*
         * لا يخرج من القياس استثناء أبدًا.
         *
         * الاستجابة أُرسلت بالفعل حين تصل هذه السطور، فالخطأ هنا لا يمسّ
         * الزائر. لكن سباق طلبين متوازيين على نفس معرّف الزيارة يرمي
         * انتهاك قيد فريد يملأ السجلّات بأعطال ليست أعطالًا. الالتقاط
         * يخدم الموقع ولا يزاحمه.
         */
        try {
            $user = $request->user();
            $recorder = app(VisitRecorder::class);

            $session = $recorder->openSession(
                $request,
                $context['visit'],
                $context['visitor'],
                (bool) $context['new_visitor'],
                $user?->id,
                (bool) $user?->isAdmin(),
            );

            $recorder->recordView($session, [
                'uuid' => $context['view'],
                'path' => $recorder->path($request),
                'url' => mb_substr($request->fullUrl(), 0, 1000),
                'route_name' => $request->route()?->getName(),
                'query_string' => $recorder->query($request),
                'referrer' => mb_substr((string) $request->headers->get('referer', ''), 0, 1000) ?: null,
                'status_code' => $response->getStatusCode(),
                'response_ms' => (int) round((microtime(true) - (float) $context['started']) * 1000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[insights] تعذّر تسجيل الزيارة', ['error' => $e->getMessage()]);
        }
    }

    /**
     * ما يُلتقط: صفحة يفتحها إنسان أو يزحف إليها بوت.
     *
     * ما لا يُلتقط: النماذج المُرسَلة والحذف والتعديل (POST وأخواته) —
     * ليست «زيارة صفحة» بل فعلًا، ومكانها جدول الأحداث لا المشاهدات.
     */
    private function shouldTrack(Request $request): bool
    {
        if (! config('insights.enabled', true)) {
            return false;
        }

        if (! $request->isMethod('GET')) {
            return false;
        }

        // نداءات الخلفية ليست مشاهدات: تسجيلها يضاعف «الصفحات لكل جلسة»
        // بلا أن يفتح الزائر صفحة واحدة إضافية.
        if ($request->ajax() || $request->wantsJson() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return false;
        }

        if (config('insights.respect_dnt', false) && $request->headers->get('DNT') === '1') {
            return false;
        }

        foreach ((array) config('insights.excluded_paths', []) as $pattern) {
            if ($request->is($pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * الاستجابة تُسجَّل إن كانت صفحة فعلًا.
     *
     * التحويلات (301/302) محطّات لا وجهات، وتسجيلها يخلق «صفحة دخول»
     * وهمية تختفي منها الصفحة التي وصل إليها الزائر حقًّا.
     *
     * أما 404 فتُسجَّل عمدًا: صفحة مفقودة يصلها زوّار هي رابط مكسور
     * يحتاج إصلاحًا، ولن يظهر في أي مكان آخر.
     */
    private function shouldRecord(Response $response): bool
    {
        $status = $response->getStatusCode();

        if ($response->isRedirection() || $status >= 500) {
            return false;
        }

        $type = (string) $response->headers->get('Content-Type', '');

        return $type === '' || str_contains($type, 'text/html');
    }
}
