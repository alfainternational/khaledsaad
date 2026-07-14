<?php

namespace App\Http\Controllers\Web;

use App\Domain\AI\Models\AIGeneration;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\Dashboard\DashboardResolver;
use App\Support\Dashboard\MonthlySeries;
use App\Support\Ui\FlashMessageCatalog;
use App\Support\Workspaces\OnboardingState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithWorkspaceContext;

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
            'dash' => $this->buildDash($workspace, $dashboard, $request->user()->name),
        ]);
    }

    public function switchWorkspace(Workspace $workspace, FlashMessageCatalog $flash): RedirectResponse
    {
        $this->authorize('switch', $workspace);

        request()->session()->put('current_workspace_id', $workspace->id);

        return back()->with('status', $flash->switchedWorkspace());
    }

    /**
     * Build the shared ecommerce dashboard view-model — same contract as the admin dashboard.
     *
     * @param  array<string, mixed>  $dashboard
     * @return array<string, mixed>
     */
    private function buildDash(Workspace $workspace, array $dashboard, string $userName): array
    {
        $project = $dashboard['currentProject'];
        $metrics = $dashboard['metrics'];
        $pipeline = collect($dashboard['toolPipeline'] ?? []);
        $brief = $dashboard['briefAssessment'] ?? ['completeness_score' => 0];
        $nextStep = $dashboard['nextStep'];

        $totalTools = (int) $pipeline->sum('total');
        $completedTools = (int) $pipeline->sum('completed');
        $journeyPct = $totalTools > 0 ? (int) round($completedTools / $totalTools * 100) : 0;

        [$months, $labels] = MonthlySeries::window(8);
        $runsByMonth = MonthlySeries::counts(
            ToolRun::query()->where('workspace_id', $workspace->id), $months
        );
        $aiByMonth = MonthlySeries::counts(
            AIGeneration::query()->where('workspace_id', $workspace->id), $months
        );
        $trRuns = MonthlySeries::trend($runsByMonth);
        $trAi = MonthlySeries::trend($aiByMonth);

        $greeting = now()->hour < 12 ? 'صباح الخير' : (now()->hour < 17 ? 'مرحباً' : 'مساء الخير');

        $distItems = $pipeline->map(fn (array $s): array => [
            'name' => $s['label'],
            'sub' => $s['completed'].'/'.$s['total'].' أداة',
            'pct' => ($s['total'] ?? 0) > 0 ? $s['completed'] / $s['total'] * 100 : 0,
            'initials' => (string) $s['stage'],
        ])->all();

        $rows = collect($dashboard['recentToolRuns'])->map(fn ($r): array => [
            'title' => $r->tool?->name ?? $r->tool_code,
            'project' => $r->project?->name,
            'author' => $r->author?->name,
            'score' => (int) ($r->completeness_score ?? 0),
            'time' => $r->created_at?->diffForHumans(),
        ])->all();

        return [
            'head' => [
                'title' => $greeting.'، '.$userName,
                'subtitle' => $project
                    ? 'أنت داخل مشروع '.$project->name.' · '.$journeyPct.'% من الرحلة مكتمل'
                    : 'ابدأ بإنشاء أول مشروع لتظهر لك قمرة القيادة الخاصة به.',
                'actions' => $project
                    ? [
                        ['label' => 'كل المشاريع', 'route' => route('projects.index'), 'variant' => 'ghost'],
                        ['label' => 'تفاصيل المشروع', 'route' => route('projects.show', $project), 'variant' => 'secondary'],
                    ]
                    : [
                        ['label' => 'إنشاء مشروع', 'route' => route('projects.create'), 'variant' => 'primary'],
                    ],
            ],
            'banner' => [
                'label' => $project ? 'الخطوة التالية' : 'الخطوة الأولى',
                'title' => $nextStep['title'],
                'body' => $nextStep['summary'],
                'cta' => ['label' => $nextStep['action_label'], 'route' => $nextStep['action_route']],
            ],
            'metrics' => [
                ['icon' => 'tool', 'tint' => 'indigo', 'label' => 'تشغيل الأدوات', 'value' => number_format($metrics['tool_runs']), 'trend' => $trRuns],
                ['icon' => 'bolt', 'tint' => 'teal', 'label' => 'مخرجات الاستوديو', 'value' => number_format($metrics['ai_generations']), 'trend' => $trAi],
            ],
            'sales' => [
                'title' => 'نشاطك الشهري',
                'sub' => 'تشغيل الأدوات عبر آخر ٨ أشهر',
                'link' => ['label' => 'الأدوات', 'route' => route('tools.index')],
            ],
            'target' => [
                'title' => 'اكتمال الرحلة',
                'sub' => 'نسبة إنجاز أدوات المشروع',
                'badge' => $trRuns,
                'caption' => 'أنجزت '.number_format($completedTools).' من '.number_format($totalTools).' أداة. كل خطوة تجعل مخرجاتك أدقّ على مقاسك.',
                'stats' => [
                    ['label' => 'مكتملة', 'value' => number_format($completedTools)],
                    ['label' => 'الإجمالي', 'value' => number_format($totalTools)],
                    ['label' => 'الملف', 'value' => ($brief['completeness_score'] ?? 0).'%'],
                ],
            ],
            'statistics' => [
                'title' => 'إحصائياتك',
                'sub' => 'نشاطك ومخرجاتك خلال آخر ٨ أشهر',
                'tabs' => ['نظرة عامة', 'الأدوات', 'الذكاء'],
                'range' => reset($labels).' – '.end($labels),
            ],
            'distribution' => [
                'title' => 'توزيع المراحل',
                'sub' => 'نسبة اكتمال كل مرحلة من رحلتك',
                'link' => ['label' => 'الأدوات', 'route' => route('tools.index')],
                'items' => $distItems,
            ],
            'table' => [
                'title' => 'آخر ما أنجزته',
                'sub' => 'أحدث تشغيلات الأدوات في مساحتك',
                'link' => ['label' => 'الأدوات', 'route' => route('tools.index')],
                'rows' => $rows,
            ],
            'charts' => [
                'sales' => [
                    'type' => 'bar', 'height' => 200, 'colors' => ['--p'],
                    'categories' => $labels,
                    'series' => [['name' => 'تشغيل الأدوات', 'data' => array_values($runsByMonth)]],
                ],
                'target' => [
                    'type' => 'radialBar', 'height' => 320, 'colors' => ['--p'], 'gradientTo' => '--teal',
                    'value' => $journeyPct, 'label' => 'اكتمال الرحلة',
                ],
                'statistics' => [
                    'type' => 'area', 'height' => 320, 'colors' => ['--p', '--teal'],
                    'categories' => $labels,
                    'series' => [
                        ['name' => 'تشغيل الأدوات', 'data' => array_values($runsByMonth)],
                        ['name' => 'مخرجات الاستوديو', 'data' => array_values($aiByMonth)],
                    ],
                    'tabs' => [
                        ['label' => 'نظرة عامة', 'series' => [
                            ['name' => 'تشغيل الأدوات', 'data' => array_values($runsByMonth)],
                            ['name' => 'مخرجات الاستوديو', 'data' => array_values($aiByMonth)],
                        ]],
                        ['label' => 'الأدوات', 'series' => [
                            ['name' => 'تشغيل الأدوات', 'data' => array_values($runsByMonth)],
                        ]],
                        ['label' => 'الذكاء', 'series' => [
                            ['name' => 'مخرجات الاستوديو', 'data' => array_values($aiByMonth)],
                        ]],
                    ],
                ],
            ],
        ];
    }
}
