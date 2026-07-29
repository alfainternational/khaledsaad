<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\AiReadiness\Jobs\ProbeAnswerPresence;
use App\Modules\AiReadiness\Models\PresenceRun;
use App\Modules\AiReadiness\QuestionBank;
use App\Modules\Measurement\PresenceMetrics;
use App\Modules\Measurement\QueryBudgetManager;
use App\Modules\Measurement\SourceMap;
use App\Policies\ProjectOwnership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * عقد تقرير الحضور للتطبيق — نفس الخدمات ونفس المفاتيح التي يقرؤها الويب.
 */
class PresenceController extends Controller
{
    /** النِّسَب تبقى عشرية حتى حين تكون قيمتها صحيحة — عميل Flutter يقرأ double. */
    private const JSON = JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE;

    public function __construct(
        private readonly PresenceMetrics $metrics,
        private readonly SourceMap $sources,
        private readonly QuestionBank $questions,
        private readonly QueryBudgetManager $budgets,
    ) {}

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $runs = $project->presenceRuns()->where('status', '!=', PresenceRun::STATUS_FAILED)->get();
        $latest = $runs->sortByDesc('started_at')->first();
        $budget = $this->budgets->budgetFor($project->workspace);

        return response()->json([
            'data' => [
                'project' => ['slug' => $project->slug, 'name' => $project->name],

                // null لا أصفار: «لم يُقَس» و«قِيس فكان صفرًا» حالتان مختلفتان.
                'metrics' => $latest === null ? null : $this->metrics->forRun($latest, $project->name),
                'source_map' => $this->sources->build($runs, $project->profile?->website),
                'questions' => $this->questions->for($project),
                'budget' => [
                    'monthly_limit' => $budget->monthly_limit,
                    'remaining' => $budget->remaining(),
                    'usage_ratio' => round($budget->usageRatio(), 4),
                ],
            ],
        ], options: self::JSON);
    }

    public function probe(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $budget = $this->budgets->budgetFor($project->workspace);
        $needed = count($this->questions->for($project)) * PresenceRun::MIN_ATTEMPTS;

        if ($budget->remaining() < $needed) {
            return response()->json([
                'message' => sprintf(
                    'بلغت مساحتك سقف استعلامات هذا الشهر (%d من %d). الاستطلاع يحتاج %d استعلامًا.',
                    $budget->committed(),
                    $budget->monthly_limit,
                    $needed,
                ),
            ], 422);
        }

        ProbeAnswerPresence::dispatch($project->id);

        return response()->json(['data' => ['queued' => true]], 202, options: self::JSON);
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(ProjectOwnership::owns($request->user(), $project), 404);
    }
}
