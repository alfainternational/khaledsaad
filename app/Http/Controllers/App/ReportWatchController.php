<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportWatcher;
use App\Services\Growth\LiveReportChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * تفعيل وإيقاف التقرير الحي — جسر «خلّيه حيًّا» في نهاية كل تقرير.
 */
class ReportWatchController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(private readonly LiveReportChecker $checker) {}

    public function store(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($request, $report);

        $this->checker->activate($report, $request->user());

        return back()->with('status', 'تقريرك الآن حي: نراقب تغيّرات مشروعك وننبهك إذا تغيّر ما بُني عليه.');
    }

    public function destroy(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($request, $report);

        ReportWatcher::where('report_id', $report->id)
            ->update(['status' => ReportWatcher::STATUS_PAUSED]);

        return back()->with('status', 'أوقفنا مراقبة هذا التقرير. يمكنك إعادتها متى شئت.');
    }
}
