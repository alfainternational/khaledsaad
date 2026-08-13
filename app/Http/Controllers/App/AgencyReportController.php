<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\AgencyReport;
use App\Models\Project;
use App\Modules\Reporting\AgencyReportDocumentAdapter;
use App\Modules\Reporting\AgencyReportPdfGenerator;
use App\Modules\Reporting\AgencyReportService;
use App\Modules\Reporting\AgencyReportSharing;
use App\Modules\Reporting\OwnerReportPdfGenerator;
use App\Modules\Reporting\ReportFreshnessService;
use App\Services\Marketing\BudgetPlanner;
use App\Services\Tools\FullDiagnosisRunner;
use App\Support\Marketing\BriefQuestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyReportController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly AgencyReportService $service,
        private readonly AgencyReportDocumentAdapter $documents,
        private readonly AgencyReportPdfGenerator $agencyPdf,
        private readonly OwnerReportPdfGenerator $ownerPdf,
        private readonly AgencyReportSharing $sharing,
        private readonly ReportFreshnessService $freshness,
    ) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);
        $project->loadMissing('profile');

        return view('app.agency-reports.index', [
            'sweep' => app(FullDiagnosisRunner::class)->preview($project),
            'project' => $project,
            'readiness' => $this->service->readiness($project),
            'reports' => $reports = $project->agencyReports()->latest('version')->get(),
            'freshnessByReport' => $reports->mapWithKeys(fn (AgencyReport $report) => [
                $report->id => $this->freshness->status($report),
            ]),
            // موجز التكليف: ما تسأل عنه الوكالة ولا تعرفه الأدوات التشخيصية.
            'briefGroups' => BriefQuestions::groups(),
            'brief' => $project->profile?->brief ?? [],
            'services' => $project->profile?->agency_services ?? [],
            'briefCompleteness' => $this->service->briefCompleteness($project),
            'budget' => app(BudgetPlanner::class)->planForProject($project),
        ]);
    }

    /**
     * حفظ موجز التكليف. منفصل عن توليد الإصدار لأنه بيانات مشروع دائمة
     * تُعاد قراءتها في كل إصدار، لا لقطة تُجمَّد مع نسخة واحدة.
     */
    public function saveBrief(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate(array_merge(BriefQuestions::rules(), [
            'brief' => 'nullable|array',
        ]));

        $this->service->saveBrief($project, $validated['brief'] ?? []);

        return back()->with('status', __('حُفظ موجز التكليف. سيدخل في الإصدار القادم من المستند.'));
    }

    /**
     * التشخيص الشامل: زر واحد يشغّل الأدوات كلها ثم يبني المستند الموحّد.
     */
    public function sweep(Request $request, Project $project, FullDiagnosisRunner $runner): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $validated = $request->validate([
            'mode' => 'nullable|in:auto,manual',
        ]);

        $result = $runner->run($project, $request->user(), $validated['mode'] ?? FullDiagnosisRunner::MODE_AUTO);

        return back()
            ->with('status', $result['message'])
            ->with('sweep', $result);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $validated = $request->validate([
            'visibility' => 'nullable|array',
            'visibility.budget' => 'nullable|in:full,summary,private',
            'visibility.competitors' => 'nullable|in:full,summary,private',
            'visibility.evidence' => 'nullable|in:full,summary,private',
        ]);

        $report = $this->service->generate(
            $project,
            $request->user(),
            $validated['visibility'] ?? [],
        );

        return redirect()->route('app.agency-reports.show', $report)
            ->with('status', __('أُنشئ تقرير جديد يشرح حالة مشروعك، ومعه موجز الوكالة عند اكتمال بياناته.'));
    }

    public function show(Request $request, AgencyReport $agencyReport): View
    {
        $this->authorizeAgencyReport($request, $agencyReport);

        return view('app.agency-reports.show', [
            'agencyReport' => $agencyReport,
            'snapshot' => $this->documents->ownerSnapshot($agencyReport),
            'share' => $this->sharing->status($agencyReport),
            'freshness' => $this->freshness->status($agencyReport),
        ]);
    }

    public function brief(Request $request, AgencyReport $agencyReport): View
    {
        $this->authorizeAgencyReport($request, $agencyReport);
        abort_unless($agencyReport->snapshot['agency_brief']['readiness']['is_ready'] ?? false, 409);

        return view('app.agency-reports.brief', [
            'agencyReport' => $agencyReport,
            'snapshot' => $agencyReport->snapshot,
            'share' => $this->sharing->status($agencyReport),
        ]);
    }

    public function briefPdf(Request $request, AgencyReport $agencyReport): StreamedResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);
        abort_unless($agencyReport->snapshot['agency_brief']['readiness']['is_ready'] ?? false, 409);

        return $this->agencyPdf->download($agencyReport);
    }

    public function pdf(Request $request, AgencyReport $agencyReport): StreamedResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);

        return $this->ownerPdf->download($agencyReport);
    }

    public function data(Request $request, AgencyReport $agencyReport): JsonResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);

        return response()->json($this->sharing->dataFile($agencyReport));
    }

    public function share(Request $request, AgencyReport $agencyReport): RedirectResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);
        $validated = $request->validate([
            'days' => 'nullable|integer|in:'.implode(',', AgencyReportSharing::EXPIRY_CHOICES),
        ]);

        $this->sharing->share($agencyReport, (int) ($validated['days'] ?? 30));

        return back()->with('status', __('أُنشئ رابط مشاركة محدود المدة. يمكنك إلغاؤه في أي وقت.'));
    }

    public function revokeShare(Request $request, AgencyReport $agencyReport): RedirectResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);
        $this->sharing->revoke($agencyReport);

        return back()->with('status', __('أُلغي الرابط فورًا ولم يعد يفتح لدى أحد.'));
    }
}
