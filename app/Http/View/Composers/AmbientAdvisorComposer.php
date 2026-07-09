<?php

namespace App\Http\View\Composers;

use App\Application\Intelligence\CompileWorkspaceIntelligenceAction;
use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Brain;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use Illuminate\View\View;

/**
 * AmbientAdvisor — يجعل العقل المحلي حاضراً في كل صفحة من المنصة.
 *
 * يعمل كـ View Composer على القالب الرئيسي: يحسب نتيجة العقل (الخطوة التالية)
 * مرة واحدة لكل عرض صفحة، مع كاش العقل الداخلي (30 دقيقة) — بلا أي عملية خلفية،
 * بلا موارد ثقيلة. هذا هو تجسيد "شغّال في كل أجزاء الموقع وفي كل الأوقات".
 */
class AmbientAdvisorComposer
{
    public function __construct(private readonly Brain $brain) {}

    public function compose(View $view): void
    {
        $workspace = app()->bound('currentWorkspace') ? app('currentWorkspace') : null;

        if (! $workspace instanceof Workspace) {
            $view->with('ambientAdvisor', null);

            return;
        }

        // المسار السريع (HTML-like): اقرأ الـ artifact المُجمَّع مسبقاً — صفر حساب.
        $snapshot = WorkspaceData::query()
            ->where('workspace_id', $workspace->getKey())
            ->whereNull('project_id')
            ->where('key', CompileWorkspaceIntelligenceAction::SNAPSHOT_KEY)
            ->value('value_json');

        if (is_array($snapshot) && ! empty($snapshot['headline'])) {
            $view->with('ambientAdvisor', $snapshot);

            return;
        }

        // احتياطي: لا artifact بعد (مساحة جديدة) → حساب حيّ خفيف لمرة واحدة.
        $result = $this->brain->think(new AgentContext(
            intent: 'next_step',
            workspace: $workspace,
            userId: auth()->id(),
        ));

        $view->with('ambientAdvisor', $result->isEmpty() ? null : $result->toArray());
    }
}
