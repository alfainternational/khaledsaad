<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Report;
use App\Support\Presentation\ReportPresenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * فهرس التقارير عبر المشاريع — القسم الثالث الذي كانت الملاحة تعد به
 * وتوجّه إلى صفحة المشاريع بدلًا منه.
 *
 * تقاريرُ المستخدم أصلُ ما دفع مقابله، وإخفاؤها خلف تنقّلٍ في المشاريع
 * يجعل ما اشتراه أصعب وصولًا مما لم يشترِه.
 */
final class ReportIndexController extends Controller
{
    public function __construct(private readonly ReportPresenter $presenter) {}

    public function index(Request $request): View
    {
        $projectIds = Project::query()
            ->whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->pluck('id');

        $filter = $request->string('project')->toString();

        $reports = Report::query()
            ->whereIn('project_id', $projectIds)
            ->where('status', 'published')
            ->when($filter !== '', fn ($query) => $query->whereHas(
                'project',
                fn ($inner) => $inner->where('slug', $filter),
            ))
            ->with(['project:id,name,slug', 'toolRun.toolVersion.tool'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('app.reports.index', [
            'reports' => $reports,
            'cards' => $reports->getCollection()->map(fn (Report $report) => [
                ...$this->presenter->card($report),
                'project' => [
                    'name' => $report->project?->name,
                    'slug' => $report->project?->slug,
                ],
            ])->all(),
            // الفلتر يُبنى من مشاريع المستخدم نفسه لا من قائمة ثابتة.
            'projects' => Project::query()
                ->whereIn('id', $projectIds)
                ->orderBy('name')
                ->get(['name', 'slug']),
            'active_project' => $filter,
        ]);
    }
}
