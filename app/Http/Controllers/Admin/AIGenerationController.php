<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Ops\AdminRetryAiGenerationAction;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAiGenerationOpsRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AIGenerationController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $generations = AIGeneration::query()
            ->with(['account', 'workspace', 'project', 'template', 'author'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->integer('template_id') > 0, fn ($query) => $query->where('template_id', $request->integer('template_id')))
            ->when($request->string('ops_review_status')->isNotEmpty(), fn ($query) => $query->where('ops_review_status', $request->string('ops_review_status')->value()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.ai-generations.index', [
            'generations' => $generations,
            'templates' => AITemplate::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function show(AIGeneration $aiGeneration): View
    {
        $aiGeneration->load(['account.owner', 'workspace', 'project', 'template', 'author']);

        return view('admin.ai-generations.show', ['generation' => $aiGeneration]);
    }

    public function updateOps(UpdateAiGenerationOpsRequest $request, AIGeneration $aiGeneration, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $request->validated();
        $tags = $this->normalizeOpsTags($data['ops_tags'] ?? null);
        $status = trim((string) ($data['ops_review_status'] ?? ''));
        $note = trim((string) ($data['ops_note'] ?? ''));

        $aiGeneration->fill([
            'ops_review_status' => $status !== '' ? $status : null,
            'ops_note' => $note !== '' ? $note : null,
            'ops_tags' => $tags,
        ]);
        $aiGeneration->save();

        $this->auditLogger->record(
            action: 'admin.ai_generation.ops_updated',
            targetType: 'ai_generation',
            targetId: $aiGeneration->getKey(),
            actor: $request->user(),
            workspace: $aiGeneration->workspace,
            meta: [
                'public_id' => $aiGeneration->public_id,
                'ops_review_status' => $aiGeneration->ops_review_status,
            ],
        );

        return back()->with('status', $flash->updated('بيانات التشغيل للمخرج'));
    }

    public function retry(
        AIGeneration $aiGeneration,
        AdminRetryAiGenerationAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $newGen = $action->handle($aiGeneration, request()->user());

        $this->auditLogger->record(
            action: 'admin.ai_generation.retried',
            targetType: 'ai_generation',
            targetId: $newGen->getKey(),
            actor: request()->user(),
            workspace: $newGen->workspace,
            meta: [
                'source_generation_id' => $aiGeneration->getKey(),
                'source_public_id' => $aiGeneration->public_id,
            ],
        );

        return redirect()
            ->route('admin.ai-generations.show', $newGen)
            ->with('status', $flash->studioDraftGenerated());
    }

    /**
     * @return list<string>|null
     */
    private function normalizeOpsTags(?string $raw): ?array
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $parts = array_map('trim', explode(',', $raw));
        $parts = array_values(array_filter($parts, fn (string $t): bool => $t !== ''));

        return $parts === [] ? null : $parts;
    }
}
