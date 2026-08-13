<?php

namespace App\Modules\Insights;

use App\Modules\Insights\Models\VisitorEvent;
use App\Modules\Insights\Models\VisitorPageView;
use App\Modules\Insights\Models\VisitorSession;
use Illuminate\Support\Collection;

/**
 * رحلة زائر واحد — من أول مرة عرفناه إلى آخر صفحة أغلقها.
 *
 * الإجماليات تقول ما يفعله «الزوّار»، وهذا يقول ما فعله **هذا الزائر**.
 * الفرق قرارٌ مختلف: متوسط الجلسة دقيقتان لا يخبرك أن العميل الذي راسلك
 * أمس قرأ صفحة التسعير ثلاث مرات في أسبوع ثم فتح صفحة الوكالات.
 */
class VisitorJourney
{
    /**
     * ملف الزائر كاملًا: من هو، ومن أين عرفنا، وماذا فعل عبر جلساته.
     *
     * @return array<string, mixed>|null
     */
    public function profile(string $visitorId): ?array
    {
        $sessions = VisitorSession::where('visitor_id', $visitorId)
            ->with('user:id,name,email')
            ->orderByDesc('started_at')
            ->get();

        if ($sessions->isEmpty()) {
            return null;
        }

        $first = $sessions->last();

        return [
            'visitor_id' => $visitorId,

            /*
             * أول مصدر لا آخره.
             *
             * من عرف الموقع من مساعد ذكاء ثم عاد مباشرةً عشر مرات، مصدره
             * «مساعدات ذكاء». نسبته إلى «مباشر» لأنها آخر زيارة يمحو
             * القناة التي جلبته أصلًا — وهي القناة التي تستحق الميزانية.
             */
            'first_seen' => $first->started_at,
            'first_channel' => $first->channel,
            'first_channel_label' => TrafficOrigin::CHANNELS[$first->channel] ?? __('غير مصنّف'),
            'first_platform' => $first->platform,
            'first_referrer' => $first->referrer_host,
            'first_campaign' => $first->campaign,
            'first_landing' => $first->entry_path,

            'last_seen' => $sessions->first()->last_activity_at,
            'sessions_count' => $sessions->count(),
            'page_views' => (int) $sessions->sum('page_views_count'),
            'events_count' => (int) $sessions->sum('events_count'),
            'total_seconds' => (int) $sessions->sum('active_seconds'),
            'conversions' => $sessions->whereNotNull('converted_at')->count(),

            // الهوية إن وُجدت: الزائر يصير شخصًا لحظة تسجيل دخوله فقط.
            'user' => $sessions->firstWhere('user_id', '!=', null)?->user,

            'device' => $sessions->first()->device_type,
            'country' => LocationInference::countryName($sessions->first()->country),
            'location_basis' => $sessions->first()->location_basis,
            'sessions' => $sessions,
        ];
    }

    /**
     * الخط الزمني لجلسة واحدة: كل صفحة وكل حدث، بترتيب وقوعه.
     *
     * الدمج ضروري: قائمة صفحات وحدها لا تُظهر أنه ضغط «ابدأ التشخيص»
     * في الصفحة الثانية ثم رجع، وقائمة أحداث وحدها لا تُظهر أين كان.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function timeline(VisitorSession $session): Collection
    {
        $views = $session->pageViews()->orderBy('viewed_at')->get()
            ->map(fn (VisitorPageView $view) => [
                'type' => 'page',
                'at' => $view->viewed_at,
                'path' => $view->path,
                'title' => $view->title,
                'seconds' => $view->active_seconds,
                'scroll' => $view->scroll_percent,
                'status' => $view->status_code,
                'sequence' => $view->sequence,
                'response_ms' => $view->response_ms,
            ]);

        $events = $session->events()->orderBy('occurred_at')->get()
            ->map(fn (VisitorEvent $event) => [
                'type' => 'event',
                'at' => $event->occurred_at,
                'path' => $event->path,
                'name' => $event->name,
                'label' => $event->label,
                'category' => $event->category,
                'is_conversion' => $event->isConversion(),
            ]);

        return $views->concat($events)->sortBy('at')->values();
    }

    /**
     * آخر الزوّار: قائمة تُقرأ سطرًا سطرًا لا رقمًا مجمّعًا.
     *
     * @return Collection<int, VisitorSession>
     */
    public function recent(int $limit = 50, bool $onlyLive = false, ?string $channel = null): Collection
    {
        $query = VisitorSession::query()->human()->with('user:id,name,email');

        if ($onlyLive) {
            $query->live();
        }

        if ($channel !== null && $channel !== '') {
            $query->where('channel', $channel);
        }

        return $query->orderByDesc('last_activity_at')->limit($limit)->get();
    }
}
