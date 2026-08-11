<?php

namespace App\Modules\Insights;

use App\Modules\Insights\Models\VisitorEvent;
use App\Modules\Insights\Models\VisitorPageView;
use App\Modules\Insights\Models\VisitorSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * مسار الكتابة الوحيد للإحصاءات.
 *
 * كل صف في جداول الزوّار يمرّ من هنا، ولا يُكتب من متحكّم ولا من قالب.
 * السبب أن «مدة البقاء» و«الارتداد» و«العائد» تعريفات لا حقول: لو كتبها
 * كل مسار بطريقته لصار للرقم الواحد ثلاث قيم بحسب من كتبه.
 *
 * ولا يرمي هذا الصنف استثناءً إلى الطلب أبدًا. عطلٌ في القياس يجب ألّا
 * يُسقط صفحة على زائر — الإحصاء يخدم الموقع ولا يعطّله.
 */
class VisitRecorder
{
    public function __construct(
        private readonly ClientProfile $client,
        private readonly TrafficOrigin $origin,
        private readonly LocationInference $location,
    ) {}

    /**
     * جلسة الزيارة: تُفتح عند أول طلب، وتُستأنف فيما بعده.
     *
     * `firstOrCreate` على المعرّف لا `create`: الطلبات المتوازية (صفحة
     * تفتح صورًا ونداءات) تصل معًا بنفس معرّف الزيارة، فلولا القيد
     * الفريد لفُتحت لها ثلاث جلسات بثلاث «صفحات دخول» مختلفة.
     */
    public function openSession(Request $request, string $visitUuid, string $visitorId, bool $newVisitor, ?int $userId, bool $isStaff): VisitorSession
    {
        $profile = $this->client->fromUserAgent($request->userAgent());
        $origin = $this->origin->fromRequest($request);
        $language = $this->location->primaryLanguage($request->headers->get('accept-language'));
        $place = $this->location->resolve(null, $request->headers->get('accept-language'));

        $identity = app(VisitorIdentity::class);

        $session = VisitorSession::firstOrCreate(
            ['uuid' => $visitUuid],
            array_merge($profile, $origin, [
                'visitor_id' => $visitorId,
                'user_id' => $userId,
                'started_at' => now(),
                'last_activity_at' => now(),
                'entry_path' => $this->path($request),
                'country' => $place['country'],
                'location_basis' => $place['basis'],
                'location_evidence' => $place['evidence'],
                'language' => $language,
                'is_returning' => ! $newVisitor,
                'is_staff' => $isStaff,
                'ip_hash' => $identity->hashIp($request->ip()),

                /*
                 * العدّادات صريحة لا متروكة لافتراضي قاعدة البيانات.
                 *
                 * `firstOrCreate` يعيد نموذجًا بلا هذه السمات حين تُترك
                 * للقاعدة، فتُقرأ `null`. و`null === 0` خطأ صامت: جعل
                 * حساب الارتداد يظنّ كل جلسة «لها أحداث» فلم تُعدّ واحدة
                 * منها مرتدّة أبدًا.
                 */
                'active_seconds' => 0,
                'page_views_count' => 0,
                'events_count' => 0,
                'is_bounce' => true,
            ]),
        );

        // تسجيل الدخول أثناء الجلسة: الزيارة نفسها تُنسب لصاحبها من لحظتها،
        // فلا تنقسم رحلة الشخص الواحد إلى «مجهول قبل الدخول» و«معروف بعده».
        if ($userId !== null && $session->user_id === null) {
            $session->forceFill(['user_id' => $userId, 'is_staff' => $isStaff])->save();
            $session->pageViews()->whereNull('user_id')->update(['user_id' => $userId, 'is_staff' => $isStaff]);
        }

        return $session;
    }

    /**
     * مشاهدة صفحة: صفٌّ لكل عرض، مرقّم بترتيبه داخل الجلسة.
     *
     * الترتيب هو ما يحوّل قائمة صفحات إلى **رحلة**: بدونه نعرف أنه زار
     * التسعير والتشخيص، ولا نعرف أيّهما دفعه إلى الآخر.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function recordView(VisitorSession $session, array $attributes): ?VisitorPageView
    {
        try {
            return DB::transaction(function () use ($session, $attributes): VisitorPageView {
                $sequence = $session->page_views_count + 1;

                $view = VisitorPageView::create(array_merge($attributes, [
                    'session_id' => $session->id,
                    'visitor_id' => $session->visitor_id,
                    'user_id' => $session->user_id,
                    'sequence' => $sequence,
                    'is_entry' => $sequence === 1,
                    'is_exit' => true,
                    'is_bot' => $session->is_bot,
                    'is_staff' => $session->is_staff,
                    'viewed_at' => now(),
                ]));

                // صفحة الخروج هي الأخيرة حتى تأتي أخرى بعدها.
                $session->pageViews()
                    ->where('id', '!=', $view->id)
                    ->where('is_exit', true)
                    ->update(['is_exit' => false]);

                $session->forceFill([
                    'page_views_count' => $sequence,
                    'exit_path' => $view->path,
                    'last_activity_at' => now(),
                ])->save();

                $this->refreshBounce($session);

                return $view;
            });
        } catch (\Throwable $e) {
            $this->swallow($e, __('تعذّر تسجيل مشاهدة صفحة'));

            return null;
        }
    }

    /**
     * نبضة المتصفح: تُحدِّث الزمن النشط وعمق التمرير.
     *
     * القيم تُسنَد ولا تُجمَع: العميل يرسل إجماليه لهذه الصفحة في كل نبضة،
     * فنبضة ضائعة أو مكرّرة لا تُنقص الرقم ولا تضاعفه. الجمع هنا كان
     * سيجعل شبكة متذبذبة تبدو قراءةً أطول.
     */
    public function heartbeat(VisitorSession $session, ?VisitorPageView $view, int $activeSeconds, int $scrollPercent, int $interactions): void
    {
        try {
            if ($view !== null) {
                $view->forceFill([
                    'active_seconds' => max($view->active_seconds, $activeSeconds),
                    'scroll_percent' => min(100, max($view->scroll_percent, $scrollPercent)),
                    'interactions' => max($view->interactions, $interactions),
                    'left_at' => now(),
                ])->save();
            }

            // زمن الجلسة = مجموع أزمنة صفحاتها، لا فارق أول طلب عن آخره.
            $total = (int) $session->pageViews()->sum('active_seconds');

            $session->forceFill([
                'active_seconds' => $total,
                'last_activity_at' => now(),
            ])->save();

            $this->refreshBounce($session);
        } catch (\Throwable $e) {
            $this->swallow($e, __('تعذّر تحديث نبضة الزيارة'));
        }
    }

    /**
     * حدث: نقرة أو إرسال أو تنزيل أو خروج إلى موقع آخر.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordEvent(VisitorSession $session, ?VisitorPageView $view, array $payload): ?VisitorEvent
    {
        try {
            $event = VisitorEvent::create([
                'session_id' => $session->id,
                'page_view_id' => $view?->id,
                'visitor_id' => $session->visitor_id,
                'user_id' => $session->user_id,
                'name' => mb_substr((string) ($payload['name'] ?? 'unknown'), 0, 60),
                'category' => mb_substr((string) ($payload['category'] ?? 'interaction'), 0, 30),
                'label' => isset($payload['label']) ? mb_substr((string) $payload['label'], 0, 191) : null,
                'path' => mb_substr((string) ($payload['path'] ?? $view?->path ?? '/'), 0, 191),
                'value' => isset($payload['value']) ? (float) $payload['value'] : null,
                'meta' => is_array($payload['meta'] ?? null) ? $payload['meta'] : null,
                'is_staff' => $session->is_staff,
                'occurred_at' => now(),
            ]);

            $session->forceFill([
                'events_count' => $session->events_count + 1,
                'last_activity_at' => now(),
            ])->save();

            // التحويل يُسجَّل مرة واحدة: الأول هو ما يُنسب للمصدر، وإعادة
            // الكتابة عند كل حدث لاحق تنقل الفضل إلى آخر ضغطة زر لا إلى
            // ما جلب الزائر أصلًا.
            if ($event->isConversion() && $session->converted_at === null) {
                $session->forceFill([
                    'conversion_name' => $event->name,
                    'converted_at' => now(),
                ])->save();
            }

            $this->refreshBounce($session);

            return $event;
        } catch (\Throwable $e) {
            $this->swallow($e, __('تعذّر تسجيل حدث زائر'));

            return null;
        }
    }

    /**
     * الارتداد بتعريفه الكامل لا بعدد الصفحات وحده.
     *
     * «صفحة واحدة» تعدّ من قرأ مقالًا كاملًا في ست دقائق ارتدادًا، وهذا
     * خطأ يقلب قراءة أي مدوّنة. التعريف هنا ثلاثي: صفحة واحدة، وبلا حدث،
     * وبزمن نشط دون العتبة — ومعروض في اللوحة بنصّه (§١٣).
     */
    private function refreshBounce(VisitorSession $session): void
    {
        $bounced = $session->page_views_count <= 1
            && $session->events_count === 0
            && $session->active_seconds < (int) config('insights.bounce_max_seconds', 5);

        if ($session->is_bounce !== $bounced) {
            $session->forceFill(['is_bounce' => $bounced])->save();
        }
    }

    public function path(Request $request): string
    {
        return mb_substr('/'.ltrim($request->path(), '/'), 0, 191);
    }

    /** سلسلة الاستعلام بعد حجب ما يُعرّف شخصًا — تعريف واحد للمسارين. */
    public function query(Request $request): ?string
    {
        return $this->origin->sanitizedQuery($request);
    }

    private function swallow(\Throwable $e, string $context): void
    {
        Log::warning('[insights] '.$context, ['error' => $e->getMessage()]);
    }
}
