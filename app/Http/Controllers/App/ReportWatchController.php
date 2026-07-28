<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\ReportWatcher;
use App\Modules\Alerts\LiveReportChecker;
use App\Services\Billing\Entitlements;
use App\Support\Billing\FeatureKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * تفعيل وإيقاف التقرير الحي — جسر «خلّيه حيًّا» في نهاية كل تقرير.
 */
class ReportWatchController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly LiveReportChecker $checker,
        private readonly Entitlements $entitlements,
    ) {}

    public function store(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($request, $report);

        $workspace = $request->user()->primaryWorkspace();
        $limit = $this->entitlements->limit($workspace, FeatureKey::WATCHERS_LIMIT);

        if ($limit !== null) {
            $active = ReportWatcher::where('user_id', $request->user()->id)
                ->where('status', ReportWatcher::STATUS_ACTIVE)
                ->where('report_id', '!=', $report->id)
                ->count();

            if ($active >= $limit) {
                return back()->withErrors([
                    'watch' => $limit === 0
                        ? 'التقرير الحي غير متاح في خطتك الحالية.'
                        : "خطتك تسمح بمتابعة {$limit} تقرير حي. أوقف متابعة تقرير آخر أو ارفع خطتك.",
                ]);
            }
        }

        $this->checker->activate($report, $request->user());

        return back()->with('status', 'فُعّلت متابعة التقرير. سننبهك إذا تغيّرت البيانات التي بُني عليها.');
    }

    public function destroy(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($request, $report);

        ReportWatcher::where('report_id', $report->id)
            ->update(['status' => ReportWatcher::STATUS_PAUSED]);

        return back()->with('status', 'توقفت متابعة التقرير. يمكنك تفعيلها مرة أخرى في أي وقت.');
    }
}
