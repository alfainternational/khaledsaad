<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Dashboard\BuildOperationalAlertsAction;
use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Dashboard\MonthlySeries;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BuildOperationalAlertsAction $operationalAlerts): View
    {
        $usersTotal = User::query()->count();
        $accountsTotal = Account::query()->count();

        [$months, $labels] = MonthlySeries::window(8);
        $usersByMonth = MonthlySeries::counts(User::query(), $months);
        $accountsByMonth = MonthlySeries::counts(Account::query(), $months);
        $runsByMonth = MonthlySeries::counts(ToolRun::query(), $months);
        $aiByMonth = MonthlySeries::counts(AIGeneration::query(), $months);

        $trUsers = MonthlySeries::trend($usersByMonth);
        $trAccounts = MonthlySeries::trend($accountsByMonth);
        $trRuns = MonthlySeries::trend($runsByMonth);

        // Monthly target: this month's tool runs toward a goal.
        $thisMonthRuns = (int) end($runsByMonth);
        $prevBest = (int) (collect(array_slice($runsByMonth, 0, -1))->max() ?: 0);
        $goal = max((int) ceil($prevBest * 1.15), 10);
        $targetPct = $goal > 0 ? min(100, (int) round($thisMonthRuns / $goal * 100)) : 0;
        $todayRuns = ToolRun::query()->whereDate('created_at', CarbonImmutable::today())->count();

        // Plan distribution (share of active-ish subscriptions).
        $plans = Plan::query()->withCount('subscriptions')->orderByDesc('subscriptions_count')->get();
        $subsTotal = max(1, (int) $plans->sum('subscriptions_count'));
        $distItems = $plans->take(6)->map(fn (Plan $p): array => [
            'name' => $p->name_ar,
            'sub' => $p->code.' · '.$p->subscriptions_count.' اشتراك',
            'pct' => $p->subscriptions_count / $subsTotal * 100,
            'initials' => mb_substr($p->name_ar, 0, 1),
        ])->all();

        $recentRuns = ToolRun::query()
            ->with(['project', 'tool', 'author'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (ToolRun $r): array => [
                'title' => $r->tool?->name ?? $r->tool_code,
                'project' => $r->project?->name,
                'author' => $r->author?->name,
                'score' => (int) ($r->completeness_score ?? 0),
                'time' => $r->created_at?->diffForHumans(),
            ])->all();

        $alerts = $operationalAlerts->handle();
        $primaryAlert = $alerts[0] ?? null;

        $activeSubscriptions = Subscription::query()->where('status', 'active')->count();
        $workspacesTotal = Workspace::query()->count();

        $dash = [
            'head' => [
                'title' => auth()->user()->name.'، مركز التحكم',
                'subtitle' => number_format($accountsTotal).' حساب · '.number_format($usersTotal).' مستخدم · '.number_format($activeSubscriptions).' اشتراك نشط · '.number_format($workspacesTotal).' مساحة عمل',
                'actions' => [
                    ['label' => 'مستخدم', 'route' => route('admin.users.create'), 'variant' => 'secondary'],
                    ['label' => 'حساب', 'route' => route('admin.accounts.create'), 'variant' => 'secondary'],
                    ['label' => 'خطة', 'route' => route('admin.plans.create'), 'variant' => 'primary'],
                ],
            ],
            'banner' => $primaryAlert ? [
                'label' => 'تنبيه تشغيلي',
                'title' => $primaryAlert['title'],
                'body' => $primaryAlert['body'],
                'cta' => ['label' => 'معالجة', 'route' => $primaryAlert['url']],
            ] : null,
            'metrics' => [
                ['icon' => 'users', 'tint' => 'indigo', 'label' => 'المستخدمون', 'value' => number_format($usersTotal), 'trend' => $trUsers],
                ['icon' => 'account', 'tint' => 'teal', 'label' => 'الحسابات', 'value' => number_format($accountsTotal), 'trend' => $trAccounts],
            ],
            'sales' => [
                'title' => 'النشاط الشهري',
                'sub' => 'تشغيل الأدوات عبر آخر ٨ أشهر',
                'link' => ['label' => 'السجل', 'route' => route('admin.tool-runs.index')],
            ],
            'target' => [
                'title' => 'الهدف الشهري',
                'sub' => 'تشغيلات هذا الشهر مقابل الهدف',
                'badge' => $trRuns,
                'caption' => 'أنجزت المنصة '.number_format($thisMonthRuns).' تشغيلاً هذا الشهر، والهدف '.number_format($goal).' تشغيل. واصلوا الوتيرة.',
                'stats' => [
                    ['label' => 'الهدف', 'value' => number_format($goal)],
                    ['label' => 'هذا الشهر', 'value' => number_format($thisMonthRuns), 'direction' => $trRuns['direction']],
                    ['label' => 'اليوم', 'value' => number_format($todayRuns)],
                ],
            ],
            'statistics' => [
                'title' => 'إحصائيات المنصة',
                'sub' => 'النمو والنشاط خلال آخر ٨ أشهر',
                'tabs' => ['نظرة عامة', 'الأدوات', 'الذكاء'],
                'range' => reset($labels).' – '.end($labels),
            ],
            'distribution' => [
                'title' => 'توزيع الخطط',
                'sub' => 'حصة كل خطة من إجمالي الاشتراكات',
                'link' => ['label' => 'الخطط', 'route' => route('admin.plans.index')],
                'items' => $distItems,
            ],
            'table' => [
                'title' => 'آخر تشغيلات الأدوات',
                'sub' => 'أحدث ما نُفّذ عبر المنصة',
                'link' => ['label' => 'الكل', 'route' => route('admin.tool-runs.index')],
                'rows' => $recentRuns,
            ],
            'charts' => [
                'sales' => [
                    'type' => 'bar', 'height' => 200, 'colors' => ['--p'],
                    'categories' => $labels,
                    'series' => [['name' => 'تشغيل الأدوات', 'data' => array_values($runsByMonth)]],
                ],
                'target' => [
                    'type' => 'radialBar', 'height' => 320, 'colors' => ['--p'], 'gradientTo' => '--teal',
                    'value' => $targetPct, 'label' => 'الهدف الشهري',
                ],
                'statistics' => [
                    'type' => 'area', 'height' => 320, 'colors' => ['--p', '--teal'],
                    'categories' => $labels,
                    'series' => [
                        ['name' => 'مستخدمون جدد', 'data' => array_values($usersByMonth)],
                        ['name' => 'حسابات جديدة', 'data' => array_values($accountsByMonth)],
                    ],
                    'tabs' => [
                        ['label' => 'نظرة عامة', 'series' => [
                            ['name' => 'مستخدمون جدد', 'data' => array_values($usersByMonth)],
                            ['name' => 'حسابات جديدة', 'data' => array_values($accountsByMonth)],
                        ]],
                        ['label' => 'الأدوات', 'series' => [
                            ['name' => 'تشغيل الأدوات', 'data' => array_values($runsByMonth)],
                        ]],
                        ['label' => 'الذكاء', 'series' => [
                            ['name' => 'مخرجات الذكاء', 'data' => array_values($aiByMonth)],
                        ]],
                    ],
                ],
            ],
        ];

        return view('admin.dashboard', ['dash' => $dash]);
    }
}
