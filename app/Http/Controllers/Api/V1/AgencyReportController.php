<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\AgencyReport;
use App\Models\Project;
use App\Services\Reports\AgencyReportPdfGenerator;
use App\Services\Reports\AgencyReportService;
use App\Services\Reports\AgencyReportSharing;
use App\Services\Reports\ReportFreshnessService;
use App\Services\Tools\FullDiagnosisRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyReportController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly AgencyReportService $service,
        private readonly AgencyReportPdfGenerator $pdf,
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

        return $this->pdf->download($agencyReport);
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
            'snapshot' => $withSnapshot ? $report->snapshot : null,
        ];
    }
}
