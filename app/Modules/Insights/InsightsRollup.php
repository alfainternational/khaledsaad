<?php

namespace App\Modules\Insights;

use App\Modules\Insights\Models\VisitorDailyStat;
use App\Modules\Insights\Models\VisitorPageView;
use App\Modules\Insights\Models\VisitorSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * التجميع اليومي والتقليم.
 *
 * الغرض بقاء التاريخ بعد ذهاب تفصيله: بعد سنة لا يحتاج أحد أن يعرف أن
 * زائرًا بعينه فتح صفحة بعينها الساعة الثالثة، ويحتاج أن يعرف أن أكتوبر
 * الماضي جاء ٤٠٪ من مساعدات الذكاء. الأول يُحذف والثاني يبقى.
 *
 * وكل صف مجمَّع قابل لإعادة البناء من الخام ما دام الخام موجودًا: التجميع
 * يُكتب بـ`updateOrCreate` لا `increment`، فتشغيله مرتين لا يضاعف شيئًا.
 */
class InsightsRollup
{
    /** أبعاد التجميع: العمود في الجلسات ⇐ اسم البُعد المخزَّن. */
    private const DIMENSIONS = [
        'channel' => 'channel',
        'platform' => 'platform',
        'device_type' => 'device',
        'country' => 'country',
        'campaign' => 'campaign',
        'referrer_host' => 'referrer',
    ];

    public function rebuildLastDays(int $days): int
    {
        $start = CarbonImmutable::now()->subDays(max(1, $days) - 1)->startOfDay();

        return $this->rebuildRange($start, CarbonImmutable::now()->endOfDay());
    }

    public function rebuildSince(string $date): int
    {
        return $this->rebuildRange(
            CarbonImmutable::parse($date)->startOfDay(),
            CarbonImmutable::now()->endOfDay(),
        );
    }

    private function rebuildRange(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $written = 0;
        $cursor = $from;

        while ($cursor->lte($to)) {
            $written += $this->rebuildDay($cursor);
            $cursor = $cursor->addDay();
        }

        return $written;
    }

    private function rebuildDay(CarbonImmutable $day): int
    {
        $start = $day->startOfDay();
        $end = $day->endOfDay();

        $sessions = VisitorSession::query()->human()->whereBetween('started_at', [$start, $end]);

        // «الإجمالي» بُعدٌ أيضًا: بدونه يحتاج كل رسم بياني عامّ استعلامًا
        // على الصفوف الخام، وهو ما يجعل اللوحة تتوقف عن الفتح مع النموّ.
        $written = $this->write($day, 'total', 'all', $sessions);

        foreach (self::DIMENSIONS as $column => $dimension) {
            $values = (clone $sessions)
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->pluck($column);

            foreach ($values as $value) {
                $written += $this->write($day, $dimension, (string) $value, (clone $sessions)->where($column, $value));
            }
        }

        $written += $this->writePages($day, $start, $end);

        return $written;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<VisitorSession>  $query
     */
    private function write(CarbonImmutable $day, string $dimension, string $value, $query): int
    {
        $row = (clone $query)
            ->selectRaw('count(*) as sessions')
            ->selectRaw('count(distinct visitor_id) as visitors')
            ->selectRaw('sum(page_views_count) as page_views')
            ->selectRaw('sum(active_seconds) as active_seconds')
            ->selectRaw('sum(case when is_bounce = 1 then 1 else 0 end) as bounces')
            ->selectRaw('sum(case when converted_at is not null then 1 else 0 end) as conversions')
            ->first();

        if ($row === null || (int) $row->sessions === 0) {
            return 0;
        }

        VisitorDailyStat::updateOrCreate(
            ['stat_date' => $day->startOfDay(), 'dimension' => $dimension, 'value' => mb_substr($value, 0, 191)],
            [
                'sessions' => (int) $row->sessions,
                'visitors' => (int) $row->visitors,
                'page_views' => (int) $row->page_views,
                'active_seconds' => (int) $row->active_seconds,
                'bounces' => (int) $row->bounces,
                'conversions' => (int) $row->conversions,
            ],
        );

        return 1;
    }

    /**
     * الصفحات تُجمَّع من جدول المشاهدات لا الجلسات: «كم زيارة لهذه
     * الصفحة» سؤالٌ لا يجيبه جدول الجلسات لأن الجلسة تحوي صفحات كثيرة.
     */
    private function writePages(CarbonImmutable $day, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $rows = VisitorPageView::query()->human()
            ->whereBetween('viewed_at', [$start, $end])
            ->select('path')
            ->selectRaw('count(*) as page_views')
            ->selectRaw('count(distinct visitor_id) as visitors')
            ->selectRaw('count(distinct session_id) as sessions')
            ->selectRaw('sum(active_seconds) as active_seconds')
            ->groupBy('path')
            ->orderByDesc('page_views')
            // سقف يومي: ذيل المسارات الطويل (روابط بمعرّفات) يُنتج آلاف
            // الصفوف بزيارة واحدة لكل منها، فيتضخّم جدول التجميع بلا فائدة.
            ->limit(300)
            ->get();

        foreach ($rows as $row) {
            VisitorDailyStat::updateOrCreate(
                ['stat_date' => $day->startOfDay(), 'dimension' => 'path', 'value' => mb_substr($row->path, 0, 191)],
                [
                    'sessions' => (int) $row->sessions,
                    'visitors' => (int) $row->visitors,
                    'page_views' => (int) $row->page_views,
                    'active_seconds' => (int) $row->active_seconds,
                    'bounces' => 0,
                    'conversions' => 0,
                ],
            );
        }

        return $rows->count();
    }

    /**
     * حذف الخام بعد مدة الاحتفاظ.
     *
     * على دفعات لا بأمر واحد: `delete` على مليون صف يقفل الجدول دقائق،
     * فتتوقف كتابة الزيارات الجارية أثناء التنظيف.
     */
    public function prune(int $days): int
    {
        $cutoff = CarbonImmutable::now()->subDays($days);
        $deleted = 0;

        do {
            $ids = VisitorSession::where('started_at', '<', $cutoff)->limit(500)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            // الحذف بالمفتاح الأجنبي يتكفّل بالمشاهدات والأحداث (cascade).
            $deleted += VisitorSession::whereIn('id', $ids)->delete();
        } while (true);

        // مشاهدات يتيمة قد تبقى إن حُذفت جلستها خارج هذا المسار.
        DB::table('visitor_page_views')->where('viewed_at', '<', $cutoff)->delete();

        return $deleted;
    }
}
