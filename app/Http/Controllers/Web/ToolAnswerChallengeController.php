<?php

namespace App\Http\Controllers\Web;

use App\Application\AI\ChallengeToolAnswerAction;
use App\Domain\Tool\Models\Tool;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * المُحاوِر الذكي (المرحلة 3): يستقبل إجابة حقل ويعيد سؤال متابعة واحداً لرفع
 * دقّتها إن كانت رخوة. تحسين اختياري غير حاجب — تُستدعى عند مغادرة حقل مهم بإجابة ضعيفة.
 */
class ToolAnswerChallengeController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function store(
        Request $request,
        Tool $tool,
        ChallengeToolAnswerAction $action,
    ): JsonResponse {
        $workspace = $this->currentWorkspace($request);
        $this->authorize('useTools', $workspace);
        abort_unless($tool->status !== 'hidden', 404);

        $validated = $request->validate([
            'field' => ['required', 'string', 'max:64'],
            'value' => ['required', 'string', 'max:2000'],
            'mode' => ['nullable', 'string', 'max:32'],
        ]);

        $question = $action->handle(
            $tool->code,
            (string) ($validated['mode'] ?? 'guided'),
            $validated['field'],
            $validated['value'],
        );

        return response()->json(['question' => $question]);
    }
}
