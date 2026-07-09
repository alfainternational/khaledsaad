<?php

namespace App\Http\Controllers\Admin;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Brain;
use App\Domain\AI\Kernel\Contracts\Skill;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\AI\Kernel\SkillRegistry;
use App\Domain\AI\Services\AiHealthChecker;
use App\Domain\AI\Services\QualityJudge;
use App\Domain\AI\Services\ToolFieldAuditor;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Throwable;

/**
 * واجهة التطوير الداخلية للنظام الذكي (AI Lab).
 *
 * وحدة تحكم المطوّر: تشغيل النواة (Brain) مباشرة بأي نيّة/إشارة، تصفّح سجلّ
 * المهارات، تشغيل أوامر التعلّم/التقطير/التجميع يدوياً، ومعاينة قاعدة المعرفة
 * الخام. آدمن فقط.
 */
class AiLabController extends Controller
{
    public function __construct(
        private readonly SkillRegistry $registry,
        private readonly KnowledgeStore $knowledge,
        private readonly AiHealthChecker $health,
        private readonly AuditLogger $auditLogger,
        private readonly ToolFieldAuditor $auditor,
    ) {}

    public function index(): View
    {
        $skills = [];
        foreach ($this->registry->all() as $skillClass) {
            try {
                /** @var Skill $instance */
                $instance = app($skillClass);
                $skills[] = ['code' => $instance->code(), 'class' => $skillClass];
            } catch (Throwable $e) {
                $skills[] = ['code' => '—', 'class' => $skillClass];
            }
        }

        return view('admin.ai-lab.index', [
            'skills' => $skills,
            'knowledge' => $this->knowledge->all(),
            'health' => $this->health->check(),
            'labResult' => session('lab_result'),
            'labInput' => session('lab_input', ['intent' => 'web_research', 'query' => '']),
            'toolAudit' => $this->auditor->audit(),
            'judgeResult' => session('judge_result'),
        ]);
    }

    public function judge(Request $request, QualityJudge $judge): RedirectResponse
    {
        $validated = $request->validate([
            'label' => 'required|string|max:200',
            'instructions' => 'nullable|string|max:500',
            'value' => 'required|string|max:2000',
        ]);

        if (! $judge->enabled()) {
            return back()->with('judge_result', ['error' => 'قاضي الجودة معطّل أو لا مزوّد LLM مهيّأ.']);
        }

        $verdict = $judge->score($validated['label'], $validated['instructions'] ?? '', $validated['value']);

        return back()->with('judge_result', $verdict ?? ['error' => 'تعذّر التقييم الآن.']);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'intent' => 'required|string|max:50',
            'query' => 'nullable|string|max:300',
            'workspace_id' => 'nullable|integer',
        ]);

        $workspace = ! empty($validated['workspace_id'])
            ? Workspace::query()->find($validated['workspace_id'])
            : null;

        try {
            $context = new AgentContext(
                intent: $validated['intent'],
                workspace: $workspace,
                userId: $request->user()?->getKey(),
                signals: ['query' => $validated['query'] ?? ''],
            );

            $result = app(Brain::class)->think($context);

            $labResult = [
                'code' => $result->code,
                'headline' => $result->headline,
                'body' => $result->body,
                'bullets' => $result->bullets,
                'confidence' => $result->confidence,
                'source' => $result->source,
                'meta' => $result->meta,
            ];
        } catch (Throwable $e) {
            $labResult = ['error' => $e->getMessage()];
        }

        $this->auditLogger->record(
            action: 'admin.ai_lab.brain_run',
            targetType: 'ai_kernel',
            actor: $request->user(),
            meta: ['intent' => $validated['intent']],
        );

        return back()->with('lab_result', $labResult)->with('lab_input', $validated);
    }

    public function command(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'command' => 'required|in:ai:learn,ai:distill,ai:compile',
        ]);

        Artisan::call($validated['command']);
        $output = trim(Artisan::output());

        $this->auditLogger->record(
            action: 'admin.ai_lab.command',
            targetType: 'ai_kernel',
            actor: $request->user(),
            meta: ['command' => $validated['command'], 'output' => $output],
        );

        return back()->with('success', $validated['command'].' — '.$output);
    }
}
