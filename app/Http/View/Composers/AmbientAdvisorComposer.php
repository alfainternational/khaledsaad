<?php

namespace App\Http\View\Composers;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Brain;
use App\Domain\Workspace\Models\Workspace;
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

        $result = $this->brain->think(new AgentContext(
            intent: 'next_step',
            workspace: $workspace,
            userId: auth()->id(),
        ));

        $view->with('ambientAdvisor', $result->isEmpty() ? null : $result->toArray());
    }
}
