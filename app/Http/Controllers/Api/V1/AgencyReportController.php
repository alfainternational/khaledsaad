<?php

namespace App\Http\Controllers\Api\V1;

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
use App\Services\Tools\FullDiagnosisRunner;
use App\Support\Marketing\BriefQuestions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json(['data' => [
            'readiness' => $this->service->readiness($project),
            'reports' => $project->agencyReports()->latest('version')->get()
                ->map(fn (AgencyReport $report) => $this->payload($report, false))->all(),
        ]]);
    }

    public function sweep(Request $request, Project $project, FullDiagnosisRunner $runner): JsonResponse
    {
        $this->authorizeProject($request, $project);
        $validated = $request->validate(['mode' => 'nullable|in:auto,manual']);

        return response()->json([
            'data' => $runner->run(
                $project,
                $request->user(),
                $validated['mode'] ?? FullDiagnosisRunner::MODE_AUTO,
            ),
        ], 202);
    }

    /**
     * حفظ موجز التكليف من التطبيق — نظير مسار الويب. يعيد جاهزية الموجز
     * المحدَّثة فورًا حتى تعرض الشاشة البنود الناقصة بلا إعادة تحميل.
     */
    public function saveBrief(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate(array_merge(BriefQuestions::rules(), [
            'brief' => 'nullable|array',
        ]));

        $this->service->saveBrief($project, $validated['brief'] ?? []);

        return response()->json([
            'data' => [
                'fields' => BriefQuestions::fields(),
                'readiness' => $this->service->briefCompleteness($project),
            ],
        ]);
    }

    /**
     * أسئلة موجز التكليف وحالته الراهنة — لتعبئة نموذج التحرير في التطبيق.
     */
    public function brief(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $project->loadMissing('profile');

        return response()->json([
            'data' => [
                'groups' => BriefQuestions::groups(),
                'saved' => $project->profile?->brief ?? [],
                'primary_goal' => $project->profile?->primary_goal,
                'agency_services' => $project->profile?->agency_services ?? [],
                'budget_includes_agency_fee' => $project->profile?->budget_includes_agency_fee,
                'readiness' => $this->service->briefCompleteness($project),
            ],
        ]);
    }

    public function store(Request $request, Project $project): JsonResponse
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

        return response()->json(['data' => $this->payload($report)], 201);
    }

    public function show(Request $request, AgencyReport $agencyReport): JsonResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);

        return response()->json(['data' => $this->payload($agencyReport)]);
    }

    public function pdf(Request $request, AgencyReport $agencyReport): StreamedResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);

        return $this->ownerPdf->download($agencyReport);
    }

    public function briefPdf(Request $request, AgencyReport $agencyReport): StreamedResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);

        return $this->agencyPdf->download($agencyReport);
    }

    public function data(Request $request, AgencyReport $agencyReport): JsonResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);

        return response()->json(['data' => $this->sharing->dataFile($agencyReport)]);
    }

    public function share(Request $request, AgencyReport $agencyReport): JsonResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);
        $validated = $request->validate([
            'days' => 'nullable|integer|in:'.implode(',', AgencyReportSharing::EXPIRY_CHOICES),
        ]);

        $this->sharing->share($agencyReport, (int) ($validated['days'] ?? 30));

        return response()->json(['data' => $this->sharing->status($agencyReport)]);
    }

    public function revokeShare(Request $request, AgencyReport $agencyReport): JsonResponse
    {
        $this->authorizeAgencyReport($request, $agencyReport);
        $this->sharing->revoke($agencyReport);

        return response()->json(['data' => $this->sharing->status($agencyReport)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AgencyReport $report, bool $withSnapshot = true): array
    {
        $briefReadiness = $report->snapshot['agency_brief']['readiness'] ?? [
            'is_ready' => false,
            'missing_count' => 6,
            'missing_critical' => [],
            'requirements' => [],
            'message' => __('أنشئ إصدارًا جديدًا بعد إكمال بيانات موجز الوكالة.'),
        ];

        return [
            'uuid' => $report->uuid,
            'project_slug' => $report->project->slug,
            'title' => $report->title,
            'version' => $report->version,
            'status' => $report->status,
            'generated_at' => $report->generated_at?->toIso8601String(),
            'visibility' => $report->visibility,
            'source_report_ids' => $report->source_report_ids,
            'freshness' => $this->freshness->status($report),
            'share' => $this->sharing->status($report),
            'documents' => [
                'owner' => [
                    // تسمية موحّدة عبر الأسطح: الويب يسمّيه «تقريرك الخاص».
                    'label' => __('تقريرك الخاص'),
                    'pdf_url' => route('api.v1.agency-reports.pdf', $report),
                ],
                'agency_brief' => [
                    'label' => __('موجز الوكالة'),
                    'is_ready' => (bool) ($briefReadiness['is_ready'] ?? false),
                    'missing_count' => (int) ($briefReadiness['missing_count'] ?? 0),
                    // البنود الناقصة بالاسم، لا مجرد عددها — نفس ما يراه الويب،
                    // حتى يعرف مستخدم التطبيق أيّ بند يُكمله بدل حجب صامت (§٤.٣).
                    'missing_critical' => array_values((array) ($briefReadiness['missing_critical'] ?? [])),
                    'requirements' => array_values((array) ($briefReadiness['requirements'] ?? [])),
                    'message' => $briefReadiness['message'] ?? null,
                    'pdf_url' => ($briefReadiness['is_ready'] ?? false)
                        ? route('api.v1.agency-reports.brief.pdf', $report)
                        : null,
                ],
            ],
            'snapshot' => $withSnapshot ? $this->documents->ownerSnapshot($report) : null,
        ];
    }
}
