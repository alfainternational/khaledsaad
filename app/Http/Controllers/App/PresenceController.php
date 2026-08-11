<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Modules\AiReadiness\Jobs\ProbeAnswerPresence;
use App\Modules\AiReadiness\Models\PresenceRun;
use App\Modules\AiReadiness\QuestionBank;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\PresenceMetrics;
use App\Modules\Measurement\QueryBudgetManager;
use App\Modules\Measurement\SourceMap;
use App\Policies\ProjectOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * تقرير الحضور في الإجابات وخريطة المصادر (المرحلة ٣).
 *
 * يجيب على «هل أخسر عملاء قبل أن يعرفوني» و«أين أنشر لأظهر». المتحكّم لا
 * يحسب شيئًا: يقرأ ما قُيِّد ويعرضه مع أساسه.
 */
class PresenceController extends Controller
{
    public function __construct(
        private readonly PresenceMetrics $metrics,
        private readonly SourceMap $sources,
        private readonly QuestionBank $questions,
        private readonly QueryBudgetManager $budgets,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        $latest = $project->presenceRuns()
            ->where('status', '!=', PresenceRun::STATUS_FAILED)
            ->latest('started_at')
            ->first();

        return view('app.presence.show', [
            'project' => $project,
            'run' => $latest,

            /*
             * لا مقاييس بلا دورة: عرض أصفار لمن لم يستطلع بعد يقرأ حكمًا
             * على نشاطه بدل أن يقرأ «لم يُقَس» (§٤.٣).
             */
            'metrics' => $latest === null ? null : $this->metrics->forRun($latest, $project->name),
            'sourceMap' => $this->sources->build(
                $project->presenceRuns()->where('status', '!=', PresenceRun::STATUS_FAILED)->get(),
                $project->profile?->website,
            ),
            'questions' => $this->questions->for($project),
            'budget' => $this->budgets->budgetFor($project->workspace),
        ]);
    }

    /**
     * بدء دورة استطلاع.
     *
     * تدخل الطابور لأنها خمسة عشر نداءً خارجيًّا (§٤.١). والرفض عند نفاد
     * الميزانية يصل المستخدم فورًا لا بعد انتظار: الحجز يقع في الجامع قبل أول
     * نداء، والرسالة تُعرض كما هي بلغة مفهومة.
     */
    public function probe(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $budget = $this->budgets->budgetFor($project->workspace);
        $needed = count($this->questions->for($project)) * PresenceRun::MIN_ATTEMPTS;

        if ($budget->remaining() < $needed) {
            return back()->withErrors([
                'budget' => (new BudgetExhausted(
                    $budget->monthly_limit,
                    $budget->committed(),
                    $needed,
                ))->getMessage(),
            ]);
        }

        ProbeAnswerPresence::dispatch($project->id);

        return redirect()
            ->route('app.presence.show', $project)
            ->with('status', __('بدأ الاستطلاع. سيظهر التقرير هنا عند اكتماله.'));
    }

    private function authorizeProject(Request $request, Project $project): void
    {
        abort_unless(ProjectOwnership::owns($request->user(), $project), 404);
    }
}
