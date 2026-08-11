<?php

namespace App\Modules\Insights;

use App\Modules\Insights\Models\VisitorEvent;
use App\Modules\Insights\Models\VisitorPageView;
use App\Modules\Insights\Models\VisitorSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * مسار القراءة الوحيد للإحصاءات.
 *
 * كل رقم يظهر في اللوحة يُحسب هنا لا في متحكّم ولا في قالب (§١٤). السبب
 * أن «معدّل الارتداد» و«متوسط مدة البقاء» تعريفات قبل أن تكون استعلامات،
 * وتوزيعها على الشاشات يعطي كل شاشة رقمها الخاص للسؤال نفسه.
 *
 * ومعه دائمًا **أساسه**: لا نسبة تخرج من هنا بلا مقامها (§١٣)، لأن
 * «ارتداد ٦٠٪» من خمس جلسات ليست معلومة بل ضوضاء تبدو معلومة.
 */
class InsightsReport
{
    private CarbonImmutable $from;

    private CarbonImmutable $to;

    public function __construct(int $days = 30)
    {
        $days = max(1, min(365, $days));

        $this->to = CarbonImmutable::now()->endOfDay();
        $this->from = $this->to->subDays($days - 1)->startOfDay();
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    public function from(): CarbonImmutable
    {
        return $this->from;
    }

    public function to(): CarbonImmutable
    {
        return $this->to;
    }

    /* ---------------------------------------------------------------
     * الإجماليات
     * --------------------------------------------------------------- */

    /**
     * @return array<string, mixed>
     */
    public function totals(): array
    {
        $sessions = $this->sessions();

        $count = (clone $sessions)->count();
        $visitors = (clone $sessions)->distinct('visitor_id')->count('visitor_id');
        $views = $this->views()->count();
        $bounces = (clone $sessions)->where('is_bounce', true)->count();
        $conversions = (clone $sessions)->whereNotNull('converted_at')->count();
        $seconds = (int) (clone $sessions)->sum('active_seconds');

        return [
            'visitors' => $visitors,
            'sessions' => $count,
            'page_views' => $views,
            'new_visitors' => (clone $sessions)->where('is_returning', false)->count(),
            'returning_visitors' => (clone $sessions)->where('is_returning', true)->count(),

            /*
             * المتوسط على الجلسات التي لها زمن مقيس فقط.
             *
             * القسمة على كل الجلسات تخلط من غادر قبل أن تصل أول نبضة
             * (فزمنه صفر) بمن قرأ خمس دقائق، فينزل المتوسط إلى رقم لا
             * يصف أحدًا. المقام معروض دائمًا مع الناتج.
             */
            'measured_sessions' => (clone $sessions)->where('active_seconds', '>', 0)->count(),
            'avg_seconds' => $this->average($seconds, (clone $sessions)->where('active_seconds', '>', 0)->count()),
            'total_seconds' => $seconds,
            'bounces' => $bounces,
            'bounce_rate' => $this->percent($bounces, $count),
            'pages_per_session' => $count > 0 ? round($views / $count, 1) : 0.0,
            'conversions' => $conversions,
            'conversion_rate' => $this->percent($conversions, $count),
            'live_now' => VisitorSession::query()->human()->live()->count(),
            'bot_hits' => VisitorSession::query()->bots()->whereBetween('started_at', [$this->from, $this->to])->count(),
        ];
    }

    /**
     * مقارنة بالمدة السابقة المساوية: الرقم وحده لا يقول اتجاهًا.
     *
     * @return array<string, array{current: int|float, previous: int|float, delta_percent: float|null}>
     */
    public function comparison(): array
    {
        $length = $this->days();
        $previousTo = $this->from->subSecond();
        $previousFrom = $previousTo->subDays($length - 1)->startOfDay();

        $previous = VisitorSession::query()->human()->whereBetween('started_at', [$previousFrom, $previousTo]);
        $current = $this->sessions();

        $rows = [
            'sessions' => [(clone $current)->count(), (clone $previous)->count()],
            'visitors' => [
                (clone $current)->distinct('visitor_id')->count('visitor_id'),
                (clone $previous)->distinct('visitor_id')->count('visitor_id'),
            ],
            'page_views' => [
                $this->views()->count(),
                VisitorPageView::query()->human()->whereBetween('viewed_at', [$previousFrom, $previousTo])->count(),
            ],
            'conversions' => [
                (clone $current)->whereNotNull('converted_at')->count(),
                (clone $previous)->whereNotNull('converted_at')->count(),
            ],
        ];

        $out = [];

        foreach ($rows as $key => [$now, $before]) {
            $out[$key] = [
                'current' => $now,
                'previous' => $before,
                // لا نسبة تغيّر من صفر: «+∞٪» ليست معلومة.
                'delta_percent' => $before > 0 ? round((($now - $before) / $before) * 100, 1) : null,
            ];
        }

        return $out;
    }

    /* ---------------------------------------------------------------
     * السلاسل الزمنية
     * --------------------------------------------------------------- */

    /**
     * سلسلة يومية مكتملة: كل يوم في المدة له نقطة ولو كانت صفرًا.
     *
     * تخطّي الأيام الفارغة يجعل الرسم يكذب — أسبوعٌ بلا زيارات يظهر خطًّا
     * صاعدًا متصلًا بين نقطتين متباعدتين.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timeline(): array
    {
        $expression = $this->dateExpression('started_at');

        $rows = $this->sessions()
            ->select(
                DB::raw("{$expression} as day"),
                DB::raw('count(*) as sessions'),
                DB::raw('count(distinct visitor_id) as visitors'),
                DB::raw('sum(page_views_count) as page_views'),
                DB::raw('sum(active_seconds) as seconds'),
                DB::raw('sum(case when is_bounce = 1 then 1 else 0 end) as bounces'),
            )
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $series = [];
        $cursor = $this->from;

        while ($cursor->lte($this->to)) {
            $key = $cursor->format('Y-m-d');
            $row = $rows[$key] ?? null;

            $series[] = [
                'date' => $key,
                'label' => $cursor->translatedFormat('j M'),
                'sessions' => (int) ($row->sessions ?? 0),
                'visitors' => (int) ($row->visitors ?? 0),
                'page_views' => (int) ($row->page_views ?? 0),
                'avg_seconds' => $this->average((int) ($row->seconds ?? 0), (int) ($row->sessions ?? 0)),
                'bounces' => (int) ($row->bounces ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return $series;
    }

    /**
     * توزيع الزيارات على ساعات اليوم وأيام الأسبوع.
     *
     * سؤال تشغيلي لا زخرفي: متى يُنشر ومتى يُطلق إعلان. يُحسب في PHP لا
     * في SQL لأن دوال الوقت تختلف بين MySQL وsqlite، ولا يستحق رقمٌ
     * كهذا استعلامين متباينين قد ينحرف أحدهما عن الآخر بصمت.
     *
     * @return array{hours: array<int, int>, weekdays: array<int, int>, peak_hour: int|null, peak_weekday: int|null}
     */
    public function rhythm(): array
    {
        $hours = array_fill(0, 24, 0);
        $weekdays = array_fill(0, 7, 0);

        $this->sessions()
            ->select(['id', 'started_at'])
            ->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$hours, &$weekdays): void {
                foreach ($chunk as $session) {
                    $hours[(int) $session->started_at->format('G')]++;
                    $weekdays[(int) $session->started_at->dayOfWeek]++;
                }
            });

        return [
            'hours' => $hours,
            'weekdays' => $weekdays,
            'peak_hour' => array_sum($hours) > 0 ? (int) array_search(max($hours), $hours, true) : null,
            'peak_weekday' => array_sum($weekdays) > 0 ? (int) array_search(max($weekdays), $weekdays, true) : null,
        ];
    }

    /* ---------------------------------------------------------------
     * من أين جاؤوا
     * --------------------------------------------------------------- */

    /**
     * @return array<int, array<string, mixed>>
     */
    public function channels(): array
    {
        return $this->breakdown('channel', 12, fn (?string $value) => TrafficOrigin::CHANNELS[$value] ?? __('غير مصنّف'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function platforms(int $limit = 15): array
    {
        return $this->breakdown('platform', $limit, fn (?string $value) => $value ?? __('بلا منصة معلنة'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function referrers(int $limit = 20): array
    {
        return $this->breakdown('referrer_host', $limit, fn (?string $value) => $value ?? __('بلا مُحيل'), skipNull: true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function campaigns(int $limit = 20): array
    {
        return $this->breakdown('campaign', $limit, fn (?string $value) => $value ?? '—', skipNull: true);
    }

    /* ---------------------------------------------------------------
     * ماذا زاروا
     * --------------------------------------------------------------- */

    /**
     * أكثر الصفحات زيارة، ومعها ما يهمّ فعلًا: كم بقوا وكم مرّروا.
     *
     * «١٢٠٠ زيارة» وحدها لا تقول إن كانت الصفحة تعمل. «١٢٠٠ زيارة
     * بمتوسط ٩ ثوان وعمق تمرير ١٥٪» تقول إنها لا تعمل.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pages(int $limit = 30): array
    {
        return VisitorPageView::query()->human()
            ->whereBetween('viewed_at', [$this->from, $this->to])
            ->select(
                'path',
                DB::raw('count(*) as views'),
                DB::raw('count(distinct visitor_id) as visitors'),
                DB::raw('sum(active_seconds) as seconds'),
                DB::raw('sum(case when active_seconds > 0 then 1 else 0 end) as measured'),
                DB::raw('avg(scroll_percent) as scroll'),
                DB::raw('sum(case when is_entry = 1 then 1 else 0 end) as entries'),
                DB::raw('sum(case when is_exit = 1 then 1 else 0 end) as exits'),
                DB::raw('avg(response_ms) as response_ms'),
            )
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path' => $row->path,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
                'measured' => (int) $row->measured,
                'avg_seconds' => $this->average((int) $row->seconds, (int) $row->measured),
                'avg_scroll' => (int) round((float) $row->scroll),
                'entries' => (int) $row->entries,
                'exits' => (int) $row->exits,
                'exit_rate' => $this->percent((int) $row->exits, (int) $row->views),
                'avg_response_ms' => (int) round((float) $row->response_ms),
            ])
            ->all();
    }

    /**
     * صفحات الدخول: أول ما رآه الزائر. هنا يُحكم على الانطباع الأول.
     *
     * @return array<int, array<string, mixed>>
     */
    public function entryPages(int $limit = 15): array
    {
        return $this->sessions()
            ->select(
                'entry_path',
                DB::raw('count(*) as sessions'),
                DB::raw('sum(case when is_bounce = 1 then 1 else 0 end) as bounces'),
                DB::raw('sum(case when converted_at is not null then 1 else 0 end) as conversions'),
            )
            ->groupBy('entry_path')
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path' => $row->entry_path,
                'sessions' => (int) $row->sessions,
                'bounces' => (int) $row->bounces,
                'bounce_rate' => $this->percent((int) $row->bounces, (int) $row->sessions),
                'conversions' => (int) $row->conversions,
            ])
            ->all();
    }

    /**
     * صفحات الخروج: آخر ما رآه قبل أن يغادر. الفجوة تُقرأ من هنا.
     *
     * @return array<int, array<string, mixed>>
     */
    public function exitPages(int $limit = 15): array
    {
        return VisitorPageView::query()->human()
            ->whereBetween('viewed_at', [$this->from, $this->to])
            ->where('is_exit', true)
            ->select('path', DB::raw('count(*) as exits'))
            ->groupBy('path')
            ->orderByDesc('exits')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => ['path' => $row->path, 'exits' => (int) $row->exits])
            ->all();
    }

    /**
     * الروابط المكسورة: ٤٠٤ يصلها زوّار فعلًا.
     *
     * لا تظهر في أي تقرير آخر، وكل صف هنا إصلاحٌ بدقيقة واحدة.
     *
     * @return array<int, array<string, mixed>>
     */
    public function brokenPaths(int $limit = 20): array
    {
        return VisitorPageView::query()
            ->whereBetween('viewed_at', [$this->from, $this->to])
            ->where('status_code', '>=', 400)
            ->select('path', 'status_code', DB::raw('count(*) as hits'), DB::raw('max(viewed_at) as last_seen'))
            ->groupBy('path', 'status_code')
            ->orderByDesc('hits')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path' => $row->path,
                'status' => (int) $row->status_code,
                'hits' => (int) $row->hits,
                'last_seen' => $row->last_seen,
            ])
            ->all();
    }

    /* ---------------------------------------------------------------
     * بماذا تصفّحوا ومن أين
     * --------------------------------------------------------------- */

    /**
     * @return array<int, array<string, mixed>>
     */
    public function devices(): array
    {
        return $this->breakdown('device_type', 8, fn (?string $value) => match ($value) {
            'desktop' => __('حاسب مكتبي'),
            'mobile' => __('جوّال'),
            'tablet' => __('لوحي'),
            default => __('غير معروف'),
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function browsers(int $limit = 12): array
    {
        return $this->breakdown('browser', $limit, fn (?string $value) => $value ?? __('غير معروف'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function systems(int $limit = 12): array
    {
        return $this->breakdown('os', $limit, fn (?string $value) => $value ?? __('غير معروف'));
    }

    /**
     * البلد: `inferred` بالكامل — من المنطقة الزمنية أو اللغة (§٤.١).
     *
     * @return array<int, array<string, mixed>>
     */
    public function countries(int $limit = 20): array
    {
        return $this->breakdown('country', $limit, fn (?string $value) => LocationInference::countryName($value));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function languages(int $limit = 12): array
    {
        return $this->breakdown('language', $limit, fn (?string $value) => $value ?? __('غير معلنة'));
    }

    /**
     * تغطية استنتاج البلد: كم جلسة أعلن متصفحها منطقتها الزمنية أصلًا.
     *
     * تُعرض مع أرقام البلدان لا بعيدًا عنها (§٤.٣): «٦٢٪ من الجلسات لها
     * إشارة موقع» تجعل القارئ يعرف على ماذا يقرأ النسب.
     *
     * @return array<string, int|float>
     */
    public function locationCoverage(): array
    {
        $total = $this->sessions()->count();
        $byTimezone = (clone $this->sessions())->where('location_basis', 'timezone')->count();
        $byLanguage = (clone $this->sessions())->where('location_basis', 'language')->count();

        return [
            'total' => $total,
            'timezone' => $byTimezone,
            'language' => $byLanguage,
            'unknown' => $total - $byTimezone - $byLanguage,
            'coverage_percent' => $this->percent($byTimezone + $byLanguage, $total),
        ];
    }

    /* ---------------------------------------------------------------
     * السلوك
     * --------------------------------------------------------------- */

    /**
     * @return array<int, array<string, mixed>>
     */
    public function events(int $limit = 25): array
    {
        $labels = (array) config('insights.conversion_events', []);

        return VisitorEvent::query()->human()
            ->whereBetween('occurred_at', [$this->from, $this->to])
            ->select(
                'name',
                'category',
                DB::raw('count(*) as hits'),
                DB::raw('count(distinct session_id) as sessions'),
            )
            ->groupBy('name', 'category')
            ->orderByDesc('hits')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'label' => $labels[$row->name] ?? $this->eventLabel($row->name),
                'category' => $row->category,
                'hits' => (int) $row->hits,
                'sessions' => (int) $row->sessions,
                'is_conversion' => array_key_exists($row->name, $labels),
            ])
            ->all();
    }

    /**
     * عمق الزيارة: كم صفحة يقرأ الزائر قبل أن يغادر.
     *
     * @return array<string, int>
     */
    public function depth(): array
    {
        $buckets = ['1' => 0, '2-3' => 0, '4-6' => 0, '7-10' => 0, '11+' => 0];

        $rows = $this->sessions()
            ->select('page_views_count', DB::raw('count(*) as total'))
            ->groupBy('page_views_count')
            ->get();

        foreach ($rows as $row) {
            $pages = (int) $row->page_views_count;
            $key = match (true) {
                $pages <= 1 => '1',
                $pages <= 3 => '2-3',
                $pages <= 6 => '4-6',
                $pages <= 10 => '7-10',
                default => '11+',
            };

            $buckets[$key] += (int) $row->total;
        }

        return $buckets;
    }

    /**
     * توزيع مدة البقاء — لا المتوسط وحده.
     *
     * المتوسط يخفي الشكل: موقعان بمتوسط دقيقتين قد يكون أحدهما «الجميع
     * دقيقتان» والآخر «نصفهم ثانيتان ونصفهم أربع دقائق»، وهما حالتان
     * تتطلبان قرارين متعاكسين.
     *
     * @return array<string, int>
     */
    public function durations(): array
    {
        $buckets = ['0-10 ث' => 0, '11-30 ث' => 0, '31-60 ث' => 0, '1-3 د' => 0, '3-10 د' => 0, '+10 د' => 0];

        $this->sessions()
            ->select(['id', 'active_seconds'])
            ->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$buckets): void {
                foreach ($chunk as $session) {
                    $seconds = (int) $session->active_seconds;
                    $key = match (true) {
                        $seconds <= 10 => __('0-10 ث'),
                        $seconds <= 30 => __('11-30 ث'),
                        $seconds <= 60 => __('31-60 ث'),
                        $seconds <= 180 => __('1-3 د'),
                        $seconds <= 600 => __('3-10 د'),
                        default => __('+10 د'),
                    };

                    $buckets[$key]++;
                }
            });

        return $buckets;
    }

    /**
     * زحف الآلات — مستبعَد من كل ما سبق، ومعروض وحده.
     *
     * هذا الجدول يجيب سؤال المحور السابع سلوكيًّا: «هل أنا مرئي للنماذج
     * أصلًا». بوت لم يزر موقعك لن يستشهد به نموذج مهما حسّنت المحتوى.
     *
     * @return array<int, array<string, mixed>>
     */
    public function crawlers(int $limit = 20): array
    {
        return VisitorSession::query()->bots()
            ->whereBetween('started_at', [$this->from, $this->to])
            ->select(
                'bot_name',
                'bot_owner',
                DB::raw('count(*) as visits'),
                DB::raw('sum(page_views_count) as pages'),
                DB::raw('max(started_at) as last_seen'),
            )
            ->groupBy('bot_name', 'bot_owner')
            ->orderByDesc('visits')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->bot_name ?? __('غير مصنّف'),
                'owner' => $row->bot_owner,
                'visits' => (int) $row->visits,
                'pages' => (int) $row->pages,
                'last_seen' => $row->last_seen,
            ])
            ->all();
    }

    /* ---------------------------------------------------------------
     * أدوات داخلية
     * --------------------------------------------------------------- */

    /**
     * تقسيم على عمود من جدول الجلسات، بأربعة أرقام لكل صف.
     *
     * الجلسات وحدها لا تكفي: قناة تجلب ألف جلسة ترتد كلّها أسوأ من قناة
     * تجلب مئة تتحوّل عشر منها. ولذلك كل صف هنا يحمل زمنه وارتداده
     * وتحويله بجانب عدده.
     *
     * @return array<int, array<string, mixed>>
     */
    private function breakdown(string $column, int $limit, callable $label, bool $skipNull = false): array
    {
        $query = $this->sessions();

        if ($skipNull) {
            $query->whereNotNull($column)->where($column, '!=', '');
        }

        $total = (clone $this->sessions())->count();

        return $query
            ->select(
                $column,
                DB::raw('count(*) as sessions'),
                DB::raw('count(distinct visitor_id) as visitors'),
                DB::raw('sum(active_seconds) as seconds'),
                DB::raw('sum(case when active_seconds > 0 then 1 else 0 end) as measured'),
                DB::raw('sum(page_views_count) as page_views'),
                DB::raw('sum(case when is_bounce = 1 then 1 else 0 end) as bounces'),
                DB::raw('sum(case when converted_at is not null then 1 else 0 end) as conversions'),
            )
            ->groupBy($column)
            ->orderByDesc('sessions')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'key' => $row->{$column},
                'label' => $label($row->{$column}),
                'sessions' => (int) $row->sessions,
                'visitors' => (int) $row->visitors,
                'share_percent' => $this->percent((int) $row->sessions, $total),
                'page_views' => (int) $row->page_views,
                'avg_seconds' => $this->average((int) $row->seconds, (int) $row->measured),
                'bounce_rate' => $this->percent((int) $row->bounces, (int) $row->sessions),
                'conversions' => (int) $row->conversions,
                'conversion_rate' => $this->percent((int) $row->conversions, (int) $row->sessions),
            ])
            ->all();
    }

    private function sessions(): Builder
    {
        return VisitorSession::query()->human()->whereBetween('started_at', [$this->from, $this->to]);
    }

    private function views(): Builder
    {
        return VisitorPageView::query()->human()->whereBetween('viewed_at', [$this->from, $this->to]);
    }

    /**
     * استخراج التاريخ من طابع زمني — بصيغة تعمل على MySQL وsqlite معًا.
     *
     * الاختبارات تعمل على sqlite والإنتاج على MySQL. استعلامٌ يصحّ في
     * أحدهما فقط يعني أن اللوحة غير مختبَرة أو أن الاختبار لا يقيسها.
     */
    private function dateExpression(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "date({$column})"
            : "DATE({$column})";
    }

    private function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round(($part / $whole) * 100, 1) : 0.0;
    }

    private function average(int $sum, int $count): int
    {
        return $count > 0 ? (int) round($sum / $count) : 0;
    }

    /** اسم حدث تلقائي بالعربية — ما ليس في قائمة التحويلات يظل مفهومًا. */
    private function eventLabel(string $name): string
    {
        return match ($name) {
            'download' => __('تنزيل ملف'),
            'outbound_click' => __('خروج إلى موقع آخر'),
            'contact_click' => __('نقرة تواصل'),
            'form_submit' => __('إرسال نموذج'),
            default => $name,
        };
    }
}
