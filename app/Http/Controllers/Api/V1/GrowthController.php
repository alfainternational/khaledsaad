<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\ContentFeedback;
use App\Models\Project;
use App\Models\PulseDigest;
use App\Models\Report;
use App\Models\ReportWatcher;
use App\Modules\AiReadiness\GeoPackGenerator;
use App\Modules\Alerts\LiveReportChecker;
use App\Services\Growth\SyntheticAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * نظير محرك النمو في الواجهة البرمجية — تطبيق Flutter يستهلك نفس الخدمات
 * التي يستهلكها الويب، بلا منطق خاص به.
 */
class GrowthController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly LiveReportChecker $checker,
        private readonly GeoPackGenerator $geo,
        private readonly SyntheticAudience $audience,
    ) {}

    public function watch(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report);

        $watcher = $this->checker->activate($report, $request->user());

        return response()->json(['data' => $this->watcherPayload($watcher)], 201);
    }

    public function unwatch(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report);

        ReportWatcher::where('report_id', $report->id)
            ->update(['status' => ReportWatcher::STATUS_PAUSED]);

        return response()->json(['data' => ['status' => ReportWatcher::STATUS_PAUSED]]);
    }

    public function feedback(Request $request, Report $report): JsonResponse
    {
        $this->authorizeReport($request, $report);

        $validated = $request->validate([
            'verdict' => 'required|in:up,down',
            'note' => 'nullable|string|max:500',
        ]);

        ContentFeedback::updateOrCreate(
            ['user_id' => $request->user()->id, 'subject_type' => Report::class, 'subject_id' => $report->id],
            ['verdict' => $validated['verdict'], 'note' => $validated['note'] ?? null],
        );

        return response()->json(['data' => ['verdict' => $validated['verdict']]], 201);
    }

    public function pulse(Request $request): JsonResponse
    {
        $workspace = $request->user()->primaryWorkspace();

        $digests = PulseDigest::where('workspace_id', $workspace->id)
            ->with('project:id,name,slug')
            ->orderByDesc('week_start')
            ->limit(30)
            ->get()
            ->map(fn (PulseDigest $digest) => [
                'id' => $digest->id,
                'week_start' => $digest->week_start->toDateString(),
                'project' => ['name' => $digest->project->name, 'slug' => $digest->project->slug],
                'items' => $digest->items,
                'next_step' => $digest->next_step,
            ]);

        return response()->json(['data' => $digests]);
    }

    public function geoShow(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        return response()->json(['data' => [
            'missing_fields' => $this->geo->missingFields($project),
            'pack' => $this->geoPayload($project),
        ]]);
    }

    public function geoGenerate(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        if (($missing = $this->geo->missingFields($project)) !== []) {
            return response()->json([
                'message' => 'أكمل ملف المشروع أولًا — الحزمة تُبنى مما كتبته أنت.',
                'missing_fields' => $missing,
            ], 422);
        }

        $this->geo->generate($project);

        return response()->json(['data' => $this->geoPayload($project->refresh())], 201);
    }

    public function geoLlms(Request $request, Project $project): Response
    {
        $this->authorizeProject($request, $project);
        $pack = $project->geoPack;

        abort_if($pack === null, 404);

        return response($pack->llms_txt, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="llms.txt"',
        ]);
    }

    public function personas(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $project->personaPanel;

        return response()->json(['data' => [
            'panel' => $panel?->only(['id', 'personas', 'source', 'generated_at']),
            'tests' => $panel?->tests()->limit(10)->get(['id', 'message', 'results', 'created_at']) ?? [],
        ]]);
    }

    public function buildPanel(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $panel = $this->audience->buildPanel($project);

        return response()->json(['data' => $panel->only(['id', 'personas', 'source', 'generated_at'])], 201);
    }

    public function personaTest(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $validated = $request->validate(['message' => 'required|string|min:10|max:1000']);

        $panel = $project->personaPanel;

        if ($panel === null) {
            return response()->json(['message' => 'ابنِ لوحة الجمهور أولًا.'], 422);
        }

        try {
            $test = $this->audience->test($panel, $validated['message'], $request->user());
        } catch (Throwable) {
            return response()->json([
                'message' => 'تعذّر إجراء الاختبار الآن. أعد المحاولة بعد قليل.',
            ], 503);
        }

        return response()->json(['data' => $test->only(['id', 'message', 'results', 'created_at'])], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function watcherPayload(ReportWatcher $watcher): array
    {
        return [
            'status' => $watcher->status,
            'last_checked_at' => $watcher->last_checked_at?->toIso8601String(),
            'last_changed_at' => $watcher->last_changed_at?->toIso8601String(),
            'changes' => $watcher->changes ?? [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function geoPayload(Project $project): ?array
    {
        return $project->geoPack?->only([
            'facts', 'faq', 'jsonld', 'llms_txt', 'credibility', 'source', 'generated_at',
        ]);
    }
}
