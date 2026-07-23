<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCompetitor;
use App\Services\Competitors\CompetitorRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * إدارة المنافسين من داخل التقرير: يؤكّد المرشّح، يستبعد غير ذي الصلة،
 * أو يضيف منافسًا محليًا سمّاه بنفسه. هذا يُكمل حلقة «نقترح ← يؤكد».
 */
class CompetitorController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(private readonly CompetitorRegistry $registry) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'names' => 'required|string|max:500',
        ]);

        $this->registry->rememberNamed($project, $data['names']);

        return back()->with('status', 'أُضيف منافسوك المحليون. سيظهرون في تقاريرك القادمة أيضًا.');
    }

    public function confirm(Request $request, ProjectCompetitor $competitor): RedirectResponse
    {
        $this->authorizeCompetitor($request, $competitor);
        $this->registry->confirm($competitor);

        return back()->with('status', "أكّدت «{$competitor->name}» كمنافس فعلي.");
    }

    public function dismiss(Request $request, ProjectCompetitor $competitor): RedirectResponse
    {
        $this->authorizeCompetitor($request, $competitor);
        $this->registry->dismiss($competitor);

        return back()->with('status', "استبعدت «{$competitor->name}» من قائمة منافسيك.");
    }

    private function authorizeCompetitor(Request $request, ProjectCompetitor $competitor): void
    {
        // ملكية المنافس من ملكية مشروعه: لا سياسة منفصلة له.
        $this->authorizeProject($request, $competitor->project);
    }
}
