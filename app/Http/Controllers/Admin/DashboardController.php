<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Dashboard\BuildOperationalAlertsAction;
use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Client\Models\Client;
use App\Domain\Comment\Models\Comment;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\Project\Models\Project;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** @var list<string> Arabic short month labels, index 1..12 */
    private const AR_MONTHS = [
        1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    public function __invoke(BuildOperationalAlertsAction $operationalAlerts): View
    {
        $stats = [
            'users' => User::query()->count(),
            'accounts' => Account::query()->count(),
            'workspaces' => Workspace::query()->count(),
            'projects' => Project::query()->count(),
            'clients' => Client::query()->count(),
            'flags' => FeatureFlag::query()->whereIn('status', ['on', 'beta'])->count(),
            'tool_runs' => ToolRun::query()->count(),
            'ai_generations' => AIGeneration::query()->count(),
            'comments' => Comment::query()->count(),
        ];

        // ── Monthly series (last 8 months) ──────────────────────────────
        $months = collect(range(7, 0))
            ->map(fn (int $back): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonths($back));
        $labels = $months->map(fn (CarbonImmutable $m): string => self::AR_MONTHS[(int) $m->format('n')])->all();

        $usersByMonth = $this->monthlySeries(User::query(), $months);
        $accountsByMonth = $this->monthlySeries(Account::query(), $months);
        $toolRunsByMonth = $this->monthlySeries(ToolRun::query(), $months);
        $aiByMonth = $this->monthlySeries(AIGeneration::query(), $months);

        // ── Trends: current month vs previous month ─────────────────────
        $trends = [
            'users' => $this->trend($usersByMonth),
            'accounts' => $this->trend($accountsByMonth),
            'tool_runs' => $this->trend($toolRunsByMonth),
            'ai_generations' => $this->trend($aiByMonth),
        ];

        // ── Monthly target gauge: this month's runs toward a goal ───────
        $thisMonthRuns = (int) end($toolRunsByMonth);
        $prevBest = collect(array_slice($toolRunsByMonth, 0, -1))->max() ?: 0;
        $goal = max((int) ceil($prevBest * 1.15), 10);
        $targetPct = $goal > 0 ? min(100, (int) round($thisMonthRuns / $goal * 100)) : 0;
        $todayRuns = ToolRun::query()->whereDate('created_at', CarbonImmutable::today())->count();

        $activeSubscriptions = Subscription::query()->where('status', 'active')->count();

        return view('admin.dashboard', [
            'stats' => $stats,
            'trends' => $trends,
            'planDistribution' => Plan::query()
                ->withCount('subscriptions')
                ->orderBy('monthly_price')
                ->get(),
            'latestAuditLogs' => AuditLog::query()
                ->with('actor', 'workspace')
                ->latest('created_at')
                ->limit(8)
                ->get(),
            'recentToolRuns' => ToolRun::query()
                ->with(['project', 'tool', 'author'])
                ->latest()
                ->limit(6)
                ->get(),
            'operationalAlerts' => $operationalAlerts->handle(),
            'charts' => [
                'sales' => [
                    'type' => 'bar',
                    'height' => 200,
                    'colors' => ['--p'],
                    'categories' => $labels,
                    'series' => [
                        ['name' => 'تشغيل الأدوات', 'data' => array_values($toolRunsByMonth)],
                    ],
                ],
                'target' => [
                    'type' => 'radialBar',
                    'height' => 320,
                    'colors' => ['--p'],
                    'gradientTo' => '--teal',
                    'value' => $targetPct,
                    'label' => 'الهدف الشهري',
                ],
                'statistics' => [
                    'type' => 'area',
                    'height' => 310,
                    'colors' => ['--p', '--teal'],
                    'categories' => $labels,
                    'series' => [
                        ['name' => 'مستخدمون جدد', 'data' => array_values($usersByMonth)],
                        ['name' => 'حسابات جديدة', 'data' => array_values($accountsByMonth)],
                    ],
                ],
            ],
            'targetMeta' => [
                'today' => $todayRuns,
                'month' => $thisMonthRuns,
                'goal' => $goal,
                'active_subscriptions' => $activeSubscriptions,
            ],
        ]);
    }

    /**
     * Count rows grouped by month, aligned to the given month buckets (zero-filled).
     *
     * @param  Collection<int, CarbonImmutable>  $months
     * @return array<string, int>  keyed by 'Y-m'
     */
    private function monthlySeries(Builder $query, Collection $months): array
    {
        $start = $months->first();

        $counts = $query
            ->where('created_at', '>=', $start)
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
     * @param  array<string, int>  $series
     * @return array{pct: float, direction: string}
     */
    private function trend(array $series): array
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
