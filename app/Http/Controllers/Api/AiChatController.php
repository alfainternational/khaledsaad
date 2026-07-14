<?php

namespace App\Http\Controllers\Api;

use App\Domain\AI\Services\AiCreditService;
use App\Domain\AI\Services\AiGatewayFactory;
use App\Domain\AI\Services\AIService;
use App\Domain\AI\Web\WebResearchService;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\AI\WorkspaceGenerationContextBuilder;
use App\Support\Tooling\ToolBlueprintCatalog;
use App\Support\Tooling\ToolInputQualityAssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiChatController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function __construct(
        private readonly AIService $ai,
        private readonly WorkspaceGenerationContextBuilder $contextBuilder,
        private readonly ToolInputQualityAssessmentService $toolInputQualityAssessmentService,
        private readonly ToolBlueprintCatalog $toolBlueprints,
        private readonly AiCreditService $credits,
    ) {}

    /**
     * فحص الرصيد قبل أي نداء LLM. يعيد استجابة خطأ عند نفاد الرصيد، أو null للمتابعة.
     */
    private function creditGuard(?Workspace $workspace, int $needed = 1): ?JsonResponse
    {
        $account = $workspace?->account;
        if ($account === null) {
            return null;
        }

        if (! $this->credits->hasBalance($account, $needed)) {
            return response()->json([
                'error' => 'نفد رصيد المساعد الذكي لهذا الحساب. رقِّ باقتك أو أضف رصيداً للمتابعة.',
                'code' => 'AI_CREDITS_EXHAUSTED',
            ], 402);
        }

        return null;
    }

    /**
     * تسجيل استهلاك الـ credits بعد نجاح التوليد.
     */
    private function chargeCredits(?Workspace $workspace, int $amount, string $reason): void
    {
        $account = $workspace?->account;
        if ($account !== null) {
            $this->credits->consume($account, $amount, $reason);
        }
    }

    public function chat(Request $request): JsonResponse
    {
        // نداء LLM متزامن — لا يقتله حد PHP الافتراضي مع مزوّد أبطأ.
        set_time_limit(150);

        $request->validate([
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string|in:user,assistant,system',
            'messages.*.content' => 'required|string|max:5000',
            'tool_key' => 'nullable|string|max:100',
            'project_id' => 'nullable|integer',
        ]);

        $workspace = $this->currentWorkspace($request);
        $messages = $request->input('messages');
        $toolKey = $request->input('tool_key', 'general');
        $projectId = $request->input('project_id');

        $contextParts = ['أنت المستشار الذكي في منصة التسويق الاستراتيجي.'];

        $knowledgeQuery = collect($messages)
            ->where('role', 'user')
            ->pluck('content')
            ->filter(fn ($content): bool => is_string($content))
            ->take(-3)
            ->implode(' ');
        $contextBlock = $this->contextBuilder->promptBlockForIds($workspace?->id, $projectId, $knowledgeQuery);
        if (trim($contextBlock) !== '') {
            $contextParts[] = $contextBlock;
        }

        if ($toolKey !== 'general') {
            $contextParts[] = "المستخدم يسأل حالياً في سياق أداة: {$toolKey}. قدم نصائح مرتبطة بهذه الأداة.";
        }

        $contextParts[] = 'أجب بالعربية بوضوح ودفء مهني (أنت مع المستخدم في نفس الفريق). ركّز على خطوة عملية واحدة في كل فقرة عند الإمكان. لا تستخدم رموز تعبيرية. لا تكرّر سؤال المستخدم حرفياً.';
        $systemPrompt = implode("\n", $contextParts);

        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt,
        ]);

        if ($guard = $this->creditGuard($workspace)) {
            return $guard;
        }

        $result = $this->ai->chat($messages);

        if (! $result['success']) {
            return response()->json(['error' => $result['message'] ?? 'حدث خطأ.'], 503);
        }

        $this->chargeCredits($workspace, 1, 'ai.chat');

        return response()->json(['response' => $result['response']]);
    }

    /**
     * بثّ رد المستشار رمزاً برمز (SSE). يبثّ فعلياً عبر مزوّد OpenAI-compatible
     * (سلسلة/BYOK)؛ وإن تعذّر يتدهور بأمان لتوليد كامل دفعة واحدة. أحداث:
     * data:{"delta":"..."} ثم data:[DONE]، أو data:{"error":"..."}.
     */
    public function chatStream(Request $request, AiGatewayFactory $factory): StreamedResponse
    {
        set_time_limit(150);

        $request->validate([
            'messages' => 'required|array|min:1',
            'messages.*.role' => 'required|string|in:user,assistant,system',
            'messages.*.content' => 'required|string|max:5000',
            'tool_key' => 'nullable|string|max:100',
            'project_id' => 'nullable|integer',
        ]);

        $workspace = $this->currentWorkspace($request);
        $messages = $request->input('messages');
        $toolKey = $request->input('tool_key', 'general');
        $projectId = $request->input('project_id');

        $contextParts = ['أنت المستشار الذكي في منصة التسويق الاستراتيجي.'];
        $knowledgeQuery = collect($messages)
            ->where('role', 'user')
            ->pluck('content')
            ->filter(fn ($content): bool => is_string($content))
            ->take(-3)
            ->implode(' ');
        $contextBlock = $this->contextBuilder->promptBlockForIds($workspace?->id, $projectId, $knowledgeQuery);
        if (trim($contextBlock) !== '') {
            $contextParts[] = $contextBlock;
        }
        if ($toolKey !== 'general') {
            $contextParts[] = "المستخدم يسأل حالياً في سياق أداة: {$toolKey}. قدم نصائح مرتبطة بهذه الأداة.";
        }
        $contextParts[] = 'أجب بالعربية بوضوح ودفء مهني. ركّز على خطوة عملية واحدة في كل فقرة عند الإمكان. لا تستخدم رموز تعبيرية. لا تكرّر سؤال المستخدم حرفياً.';
        $systemPrompt = implode("\n", $contextParts);

        $lastUser = (string) (collect($messages)
            ->where('role', 'user')
            ->pluck('content')
            ->filter(fn ($c): bool => is_string($c) && trim($c) !== '')
            ->last() ?? '');

        $headers = [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        if ($guard = $this->creditGuard($workspace)) {
            return response()->stream(function (): void {
                echo 'data: '.json_encode(['error' => 'نفد رصيد المساعد الذكي لهذا الحساب.', 'code' => 'AI_CREDITS_EXHAUSTED'], JSON_UNESCAPED_UNICODE)."\n\n";
                echo "data: [DONE]\n\n";
            }, 200, $headers);
        }

        $gateway = $factory->firstStreamable($workspace?->account);
        $fullMessages = array_merge([['role' => 'system', 'content' => $systemPrompt]], $messages);

        return response()->stream(function () use ($gateway, $lastUser, $systemPrompt, $fullMessages, $workspace): void {
            $streamed = false;
            if ($gateway !== null && $lastUser !== '') {
                $streamed = $gateway->streamText($lastUser, $systemPrompt, function (string $delta): void {
                    echo 'data: '.json_encode(['delta' => $delta], JSON_UNESCAPED_UNICODE)."\n\n";
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();
                });
            }

            // تدهور آمن: مزوّد غير قابل للبثّ (مثل private_worker) ⇒ توليد كامل دفعة واحدة.
            if (! $streamed) {
                $result = $this->ai->chat($fullMessages);
                $text = ($result['success'] ?? false) ? (string) ($result['response'] ?? '') : '';
                if ($text === '') {
                    echo 'data: '.json_encode(['error' => 'تعذّر توليد رد الآن.'], JSON_UNESCAPED_UNICODE)."\n\n";
                    echo "data: [DONE]\n\n";

                    return;
                }
                echo 'data: '.json_encode(['delta' => $text], JSON_UNESCAPED_UNICODE)."\n\n";
            }

            $this->chargeCredits($workspace, 1, 'ai.chat_stream');
            echo "data: [DONE]\n\n";
        }, 200, $headers);
    }

    public function analyzeToolInputs(Request $request): JsonResponse
    {
        $request->validate([
            'tool_code' => 'required|string|max:100',
            'tool_name' => 'required|string|max:200',
            'inputs' => 'required|array',
            'mode' => 'nullable|string|max:50',
            'project_id' => 'nullable|integer',
            'enrich' => 'nullable|boolean',
        ]);

        $workspace = $this->currentWorkspace($request);

        $toolCode = $request->string('tool_code')->toString();
        $mode = $request->input('mode');

        $result = $this->toolInputQualityAssessmentService->assess(
            toolCode: $toolCode,
            toolName: $request->input('tool_name'),
            inputs: $request->input('inputs'),
            mode: $mode,
            workspaceId: $workspace?->id,
            projectId: $request->input('project_id'),
        );

        $fieldLabelMap = $this->toolBlueprints->fieldLabelMap(
            $toolCode,
            is_string($mode) ? $mode : null,
        );

        $result['labeled_inputs'] = $this->buildLabeledInputsSnapshot(
            $request->input('inputs', []),
            $fieldLabelMap,
        );
        $result['narrative_enriched'] = false;

        // التقييم المنظّم أعلاه محلي بالكامل (بدون LLM) ولا يستهلك رصيداً.
        // الاستهلاك فقط على طبقة الصقل النصي عبر LLM إن طُلبت وتوفّر رصيد.
        $wantEnrich = $request->boolean('enrich', true)
            && $this->creditGuard($workspace) === null;

        if ($wantEnrich) {
            $enriched = $this->ai->enrichToolAssessmentNarrative(
                $result,
                $toolCode,
                (string) $request->input('tool_name'),
                $request->input('inputs', []),
                $fieldLabelMap,
                $workspace?->id,
                $request->input('project_id'),
            );

            if (is_array($enriched)) {
                $result['verdict'] = $enriched['verdict'];
                $result['strategic_note'] = $enriched['strategic_note'];
                $result['narrative_enriched'] = true;
                $this->chargeCredits($workspace, 1, 'ai.tool_assessment_enrich');
            }
        }

        return response()->json([
            'analysis' => $result,
            'structured' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @param  array<string, array{label: string, answer_tip: string}>  $fieldLabelMap
     * @return array<int, array{key: string, label: string, filled: bool, preview: string}>
     */
    private function buildLabeledInputsSnapshot(array $inputs, array $fieldLabelMap): array
    {
        $rows = [];
        foreach ($inputs as $key => $value) {
            if ($key === 'brief') {
                continue;
            }
            if (! is_string($key)) {
                continue;
            }
            $v = is_string($value) ? trim($value) : '';
            $meta = $fieldLabelMap[$key] ?? null;
            $label = is_array($meta) ? ($meta['label'] ?? $key) : $key;
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'filled' => $v !== '',
                'preview' => $v !== '' ? Str::limit($v, 160, '…') : '',
            ];
        }

        return $rows;
    }

    public function research(Request $request, WebResearchService $research): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2|max:200',
            'depth' => 'nullable|integer|min:1|max:5',
        ]);

        $workspace = $this->currentWorkspace($request);

        if ($guard = $this->creditGuard($workspace)) {
            return $guard;
        }

        $data = $research->research(
            $request->string('query')->toString(),
            (int) $request->integer('depth', 3),
        );

        if (empty($data['findings'])) {
            return response()->json([
                'error' => $data['summary'] ?? 'تعذّر البحث الحيّ الآن.',
                'code' => 'WEB_RESEARCH_EMPTY',
            ], 503);
        }

        $this->chargeCredits($workspace, 1, 'ai.web_research');

        return response()->json(['research' => $data]);
    }

    public function suggestFields(Request $request): JsonResponse
    {
        $request->validate([
            'tool_code' => 'required|string|max:100',
            'tool_name' => 'required|string|max:200',
            'inputs' => 'required|array',
            'project_id' => 'nullable|integer',
            'mode' => 'nullable|string|max:50',
        ]);

        $workspace = $this->currentWorkspace($request);

        $toolCode = $request->string('tool_code')->toString();
        $blueprint = $this->toolBlueprints->for($toolCode);
        $mode = $request->input('mode');
        $fieldLabelMap = $this->toolBlueprints->fieldLabelMap($toolCode, is_string($mode) ? $mode : null);
        $outcome = trim((string) ($blueprint['outcome'] ?? ''));
        $modeLabel = null;
        if (is_string($mode) && $mode !== '' && ! empty($blueprint['modes'][$mode]['label'])) {
            $modeLabel = (string) $blueprint['modes'][$mode]['label'];
        }

        if ($guard = $this->creditGuard($workspace)) {
            return $guard;
        }

        $result = $this->ai->generateFieldSuggestions(
            toolCode: $toolCode,
            toolName: $request->input('tool_name'),
            currentInputs: $request->input('inputs'),
            workspaceId: $workspace?->id,
            projectId: $request->input('project_id'),
            fieldLabelMap: $fieldLabelMap,
            toolOutcomeHint: $outcome !== '' ? $outcome : null,
            modeLabel: $modeLabel,
        );

        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? 'فشل التوليد.'], 503);
        }

        $this->chargeCredits($workspace, 1, 'ai.field_suggestions');

        return response()->json([
            'suggestions' => $result['suggestions'] ?? [],
            'insight' => $result['insight'] ?? '',
        ]);
    }
}
