<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Recommendation;
use App\Models\Report;
use App\Modules\Reporting\ReportPdfGenerator;
use App\Services\Billing\Entitlements;
use App\Services\Growth\NextToolSuggester;
use App\Services\Tools\ToolRunService;
use App\Support\Billing\FeatureKey;
use App\Support\Presentation\ReportPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ReportPresenter $presenter,
        private readonly ToolRunService $runs,
        private readonly ReportPdfGenerator $pdf,
        private readonly NextToolSuggester $suggester,
    ) {}

    public function show(Request $request, Report $report): View
    {
        $this->authorizeReport($request, $report);

        return view('app.reports.show', [
            'report' => $this->presenter->full($report),
            'comparison' => $this->presenter->comparison($report, $this->presenter->previousFor($report)),
            // محرك النمو: حالة المراقبة، تقييم المستخدم، والأداة المقترحة تاليًا.
            'watcher' => $report->watcher,
            'myVerdict' => $report->feedback()
                ->where('user_id', $request->user()->id)
                ->value('verdict'),
            'suggestion' => $this->suggester->suggest($report->project),
        ]);
    }

    public function convert(Request $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($request, $report);

        $recommendationId = $request->integer('recommendation_id');

        if ($recommendationId > 0) {
            $recommendation = Recommendation::where('report_id', $report->id)->findOrFail($recommendationId);
            $this->runs->convertRecommendation($recommendation, $request->user());
            $message = 'أُضيفت التوصية إلى قائمة مهامك.';
        } else {
            $tasks = $this->runs->convertTopRecommendations($report, $request->user());
            $message = count($tasks).' توصيات أصبحت مهامًا بمواعيد نهائية.';
        }

        return redirect()->route('app.projects.tasks', $report->project)->with('status', $message);
    }

    /**
     * الملكية تُفحص قبل الاستحقاق: من لا يملك التقرير يرى 404، لا دعوة ترقية
     * تكشف أن التقرير موجود.
     */
    public function pdf(Request $request, Report $report): StreamedResponse|RedirectResponse
    {
        $this->authorizeReport($request, $report);

        $workspace = $report->project->workspace;

        if (! app(Entitlements::class)->allows($workspace, FeatureKey::REPORTS_PDF)) {
            return redirect()->route('app.billing')
                ->withErrors(['feature' => 'تصدير PDF غير متاح في خطتك الحالية.']);
        }

        return $this->pdf->download($report);
    }
}
