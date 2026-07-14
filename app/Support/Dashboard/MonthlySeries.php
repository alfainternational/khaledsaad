<?php

namespace App\Support\Dashboard;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds zero-filled monthly count series and month-over-month trends,
 * shared by the admin and user dashboards so both read identically.
 */
class MonthlySeries
{
    /** @var array<int, string> Arabic short month labels (index 1..12) */
    private const AR_MONTHS = [
        1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    /**
     * The last $count month buckets (oldest → newest) plus Arabic labels.
     *
     * @return array{0: Collection<int, CarbonImmutable>, 1: list<string>}
     */
    public static function window(int $count = 8): array
    {
        $months = collect(range($count - 1, 0))
            ->map(fn (int $back): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonths($back))
            ->values();

        $labels = $months
            ->map(fn (CarbonImmutable $m): string => self::AR_MONTHS[(int) $m->format('n')])
            ->all();

        return [$months, $labels];
    }

    /**
     * Count rows per month for the given query, aligned & zero-filled to $months.
     *
     * @param  Collection<int, CarbonImmutable>  $months
     * @return array<string, int>  keyed by 'Y-m'
     */
    public static function counts(Builder $query, Collection $months): array
    {
        $counts = $query
            ->where('created_at', '>=', $months->first())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as aggregate")
            ->groupBy('ym')
            ->pluck('aggregate', 'ym');

        return $months
            ->mapWithKeys(fn (CarbonImmutable $m): array => [
                $m->format('Y-m') => (int) ($counts[$m->format('Y-m')] ?? 0),
            ])
            ->all();
    }

    /**
     * Month-over-month trend (current vs previous bucket).
     *
     * @param  array<string, int>  $series
     * @return array{pct: float, direction: 'up'|'down'|'flat'}
     */
    public static function trend(array $series): array
    {
        $values = array_values($series);
        $current = (int) ($values[count($values) - 1] ?? 0);
        $previous = (int) ($values[count($values) - 2] ?? 0);

        if ($previous === 0) {
            $pct = $current > 0 ? 100.0 : 0.0;
        } else {
            $pct = round(($current - $previous) / $previous * 100, 1);
        }

        return [
            'pct' => abs($pct),
            'direction' => $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'),
        ];
    }
}
