<?php

namespace App\Http\Controllers\Web;

use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\Dashboard\DashboardResolver;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\OnboardingState;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithWorkspaceContext;

    /** @var list<string> */
    private const AR_MONTHS = [
        1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
    ];

    public function __invoke(
        Request $request,
        OnboardingState $state,
        DashboardResolver $dashboardResolver,
    ): View|RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        $workspace->loadMissing('account.subscription.plan');

        if (! $state->isCompleted($workspace)) {
            return redirect()->route('onboarding.show');
        }

        $dashboard = $dashboardResolver->resolve($workspace, $request->user());

        return view('app.dashboard', [
            'workspace' => $workspace,
            'availableWorkspaces' => $request->user()->activeWorkspaces()->with('account.subscription.plan')->get(),
            'dashboard' => $dashboard,
            'charts' => $this->buildCharts($workspace, $dashboard),
        ]);
    }

    public function switchWorkspace(Workspace $workspace, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('switch', $workspace);

        request()->session()->put('current_workspace_id', $workspace->id);

        return back()->with('status', $flash->switchedWorkspace());
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @return array<string, mixed>
     */
    private function buildCharts(Workspace $workspace, array $dashboard): array
    {
        $toolPipeline = collect($dashboard['toolPipeline'] ?? []);
        $totalTools = (int) $toolPipeline->sum('total');
        $completedTools = (int) $toolPipeline->sum('completed');
        $journeyPct = $totalTools > 0 ? (int) round($completedTools / $totalTools * 100) : 0;

        // Monthly workspace activity (last 8 months, zero-filled).
        $months = collect(range(7, 0))
            ->map(fn (int $back): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonths($back));
        $labels = $months->map(fn (CarbonImmutable $m): string => self::AR_MONTHS[(int) $m->format('n')])->all();
        $runsByMonth = $this->monthlyRuns($workspace, $months);

        return [
            'progress' => [
                'type' => 'radialBar',
                'height' => 300,
                'colors' => ['--p'],
                'gradientTo' => '--teal',
                'value' => $journeyPct,
                'label' => 'اكتمال الرحلة',
            ],
            'stages' => [
                'type' => 'bar',
                'height' => 260,
                'colors' => ['--p', '--teal'],
                'categories' => $toolPipeline->pluck('label')->all(),
                'series' => [
                    ['name' => 'مكتملة', 'data' => $toolPipeline->pluck('completed')->map(fn ($v) => (int) $v)->all()],
                    ['name' => 'إجمالي', 'data' => $toolPipeline->pluck('total')->map(fn ($v) => (int) $v)->all()],
                ],
            ],
            'activity' => [
                'type' => 'area',
                'height' => 280,
                'colors' => ['--teal'],
                'categories' => $labels,
                'series' => [
                    ['name' => 'تشغيل الأدوات', 'data' => array_values($runsByMonth)],
                ],
            ],
        ];
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $months
     * @return array<string, int>
     */
    private function monthlyRuns(Workspace $workspace, Collection $months): array
    {
        $counts = ToolRun::query()
            ->where('workspace_id', $workspace->id)
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
}
