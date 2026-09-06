<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Insights\InsightsReport;
use App\Modules\Insights\LocationInference;
use App\Modules\Insights\Models\VisitorSession;
use App\Modules\Insights\TrafficOrigin;
use App\Modules\Insights\VisitorJourney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * لوحة إحصاءات الزوّار.
 *
 * لا يُحسب هنا رقم واحد (§١٤): المتحكّم يختار المدة ويستدعي `InsightsReport`
 * ويمرّر. أي حساب يتسلّل إلى هنا يصير رقمًا بلا اختبار وبلا تعريف مشترك.
 */
class AdminInsightsController extends Controller
{
    /** المدد المتاحة: أزرار لا حقل حرّ، فالمدة القصيرة جدًّا تُنتج نسبًا كاذبة. */
    private const RANGES = [7 => 'آخر ٧ أيام', 30 => 'آخر ٣٠ يومًا', 90 => 'آخر ٩٠ يومًا', 365 => 'آخر سنة'];

    public function index(Request $request): View
    {
        $days = $this->days($request);
        $report = new InsightsReport($days);

        return view('admin.insights.index', [
            'days' => $days,
            'ranges' => self::RANGES,
            'from' => $report->from(),
            'to' => $report->to(),
            'totals' => $report->totals(),
            'audience' => $report->audience(),
            'comparison' => $report->comparison(),
            'timeline' => $report->timeline(),
            'channels' => $report->channels(),
            'platforms' => $report->platforms(),
            'referrers' => $report->referrers(),
            'campaigns' => $report->campaigns(),
            'pages' => $report->pages(),
            'entry_pages' => $report->entryPages(),
            'exit_pages' => $report->exitPages(),
            'broken' => $report->brokenPaths(),
            'devices' => $report->devices(),
            'browsers' => $report->browsers(),
            'systems' => $report->systems(),
            'countries' => $report->countries(),
            'languages' => $report->languages(),
            'coverage' => $report->locationCoverage(),
            'events' => $report->events(),
            'depth' => $report->depth(),
            'durations' => $report->durations(),
            'rhythm' => $report->rhythm(),
            'crawlers' => $report->crawlers(),
        ]);
    }

    /** قائمة الزيارات: السطر الواحد الذي يقف خلف كل رقم مجمَّع. */
    public function visitors(Request $request): View
    {
        $query = VisitorSession::query()->human()->with('user:id,name,email');

        if ($channel = $request->string('channel')->toString()) {
            $query->where('channel', $channel);
        }

        if ($request->boolean('converted')) {
            $query->whereNotNull('converted_at');
        }

        if ($request->boolean('live')) {
            $query->live();
        }

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('entry_path', 'like', "%{$search}%")
                    ->orWhere('referrer_host', 'like', "%{$search}%")
                    ->orWhere('campaign', 'like', "%{$search}%")
                    ->orWhere('visitor_id', $search);
            });
        }

        return view('admin.insights.visitors', [
            'sessions' => $query->orderByDesc('last_activity_at')->paginate(40)->withQueryString(),
            'channels' => TrafficOrigin::CHANNELS,
            'filters' => [
                'channel' => $request->string('channel')->toString(),
                'converted' => $request->boolean('converted'),
                'live' => $request->boolean('live'),
                'q' => $request->string('q')->toString(),
            ],
            'live_now' => VisitorSession::query()->human()->live()->count(),
        ]);
    }

    public function visitor(string $visitorId, VisitorJourney $journey): View
    {
        $profile = $journey->profile($visitorId);

        abort_if($profile === null, 404);

        return view('admin.insights.visitor', [
            'profile' => $profile,
            'channels' => TrafficOrigin::CHANNELS,
        ]);
    }

    public function session(string $uuid, VisitorJourney $journey): View
    {
        $session = VisitorSession::where('uuid', $uuid)->with('user:id,name,email')->firstOrFail();

        return view('admin.insights.session', [
            'session' => $session,
            'timeline' => $journey->timeline($session),
            'channel_label' => TrafficOrigin::CHANNELS[$session->channel] ?? 'غير مصنّف',
            'country_label' => LocationInference::countryName($session->country),
        ]);
    }

    /**
     * تصدير الزيارات كملف CSV — البيانات ملكُ صاحبها لا رهينة اللوحة.
     *
     * بثّ لا تجميع في الذاكرة: تصدير سنة كاملة قد يكون مئات الآلاف من
     * الصفوف، وبناؤها كلها في مصفوفة يُسقط العملية على أكبر حساب تحديدًا.
     */
    public function export(Request $request): StreamedResponse
    {
        $report = new InsightsReport($this->days($request));

        $query = VisitorSession::query()->human()
            ->whereBetween('started_at', [$report->from(), $report->to()])
            ->with('user:id,name,email')
            ->orderBy('started_at');

        $name = 'visitors-'.$report->from()->format('Ymd').'-'.$report->to()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'wb');

            // BOM: بدونه تفتح إكسل العربية كطلاسم على ويندوز.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('المعرّف'), __('الزائر'), __('المستخدم'), __('البداية'), __('آخر نشاط'), __('الثواني النشطة'),
                __('الصفحات'), __('الأحداث'), __('القناة'), __('المنصة'), __('المُحيل'), __('الحملة'),
                __('صفحة الدخول'), __('صفحة الخروج'), __('الجهاز'), __('المتصفح'), __('النظام'),
                __('البلد (فرضية)'), __('أساس البلد'), __('اللغة'), __('عائد'), __('ارتداد'), __('التحويل'),
                __('أساس التحقّق'),
            ]);

            $query->chunk(500, function ($chunk) use ($handle): void {
                foreach ($chunk as $session) {
                    fputcsv($handle, [
                        $session->uuid,
                        $session->visitor_id,
                        $session->user?->email ?? '—',
                        $session->started_at?->format('Y-m-d H:i'),
                        $session->last_activity_at?->format('Y-m-d H:i'),
                        $session->active_seconds,
                        $session->page_views_count,
                        $session->events_count,
                        TrafficOrigin::CHANNELS[$session->channel] ?? $session->channel,
                        $session->platform ?? '—',
                        $session->referrer_host ?? '—',
                        $session->campaign ?? '—',
                        $session->entry_path,
                        $session->exit_path ?? '—',
                        $session->device_type,
                        $session->browser ?? '—',
                        $session->os ?? '—',
                        LocationInference::countryName($session->country),
                        $session->location_basis ?? '—',
                        $session->language ?? '—',
                        $session->is_returning ? __('نعم') : __('لا'),
                        $session->is_bounce ? __('نعم') : __('لا'),
                        $session->conversion_name ?? '—',
                        match ($session->verified_by) {
                            'form' => __('نموذج مُرسَل'),
                            'beacon', 'backfill' => __('نبض المتصفح'),
                            default => '—',
                        },
                    ]);
                }
            });

            fclose($handle);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * المباشر: من على الموقع الآن، وأين هو تحديدًا.
     *
     * يُستدعى من الصفحة كل عشر ثوان، فيردّ JSON لا صفحة كاملة.
     */
    public function live(VisitorJourney $journey): JsonResponse
    {
        $sessions = $journey->recent(30, onlyLive: true);

        return response()->json([
            'count' => $sessions->count(),
            'at' => now()->format('H:i:s'),
            'visitors' => $sessions->map(fn (VisitorSession $session) => [
                'uuid' => $session->uuid,
                'path' => $session->exit_path ?? $session->entry_path,
                'channel' => TrafficOrigin::CHANNELS[$session->channel] ?? $session->channel,
                'platform' => $session->platform,
                'device' => $session->device_type,
                'country' => LocationInference::countryName($session->country),
                'pages' => $session->page_views_count,
                'seconds' => $session->active_seconds,
                'user' => $session->user?->name,
                'since' => $session->started_at->diffForHumans(),
            ])->all(),
        ]);
    }

    private function days(Request $request): int
    {
        $days = $request->integer('days', 30);

        return array_key_exists($days, self::RANGES) ? $days : 30;
    }
}
