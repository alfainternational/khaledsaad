<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Ops\AdminRetryToolRunAction;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Tool\Models\ToolRun;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateToolRunOpsRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ToolRunController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $toolRuns = ToolRun::query()
            ->with(['workspace', 'project', 'tool', 'author'])
            ->when($request->string('tool_code')->isNotEmpty(), fn ($query) => $query->where('tool_code', $request->string('tool_code')->value()))
            ->when($request->string('workspace_id')->isNotEmpty(), fn ($query) => $query->where('workspace_id', $request->integer('workspace_id')))
            ->when($request->string('ops_review_status')->isNotEmpty(), fn ($query) => $query->where('ops_review_status', $request->string('ops_review_status')->value()))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $toolCodes = ToolRun::query()->select('tool_code')->distinct()->pluck('tool_code');

        return view('admin.tool-runs.index', [
            'toolRuns' => $toolRuns,
            'toolCodes' => $toolCodes,
        ]);
    }

    public function show(ToolRun $toolRun): View
    {
        $toolRun->load(['workspace.account', 'project.client', 'tool', 'author']);

        return view('admin.tool-runs.show', ['toolRun' => $toolRun]);
    }

    public function updateOps(UpdateToolRunOpsRequest $request, ToolRun $toolRun, FlashMessageCatalog $flash): RedirectResponse
    {
        $data = $request->validated();
        $tags = $this->normalizeOpsTags($data['ops_tags'] ?? null);
        $status = trim((string) ($data['ops_review_status'] ?? ''));
        $note = trim((string) ($data['ops_note'] ?? ''));

        $toolRun->fill([
            'ops_review_status' => $status !== '' ? $status : null,
            'ops_note' => $note !== '' ? $note : null,
            'ops_tags' => $tags,
        ]);
        $toolRun->save();

        $this->auditLogger->record(
            action: 'admin.tool_run.ops_updated',
            targetType: 'tool_run',
            targetId: $toolRun->getKey(),
            actor: $request->user(),
            workspace: $toolRun->workspace,
            meta: [
                'public_id' => $toolRun->public_id,
                'ops_review_status' => $toolRun->ops_review_status,
            ],
        );

        return back()->with('status', $flash->updated('بيانات التشغيل للسجل'));
    }

    public function retry(
        ToolRun $toolRun,
        AdminRetryToolRunAction $action,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $newRun = $action->handle($toolRun, request()->user());

        $this->auditLogger->record(
            action: 'admin.tool_run.retried',
            targetType: 'tool_run',
            targetId: $newRun->getKey(),
            actor: request()->user(),
            workspace: $newRun->workspace,
            meta: [
                'source_run_id' => $toolRun->getKey(),
                'source_public_id' => $toolRun->public_id,
            ],
        );

        return redirect()
            ->route('admin.tool-runs.show', $newRun)
            ->with('status', $flash->created('تشغيل أداة جديد من إعادة التنفيذ'));
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
