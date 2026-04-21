<?php

namespace App\Http\Controllers\Admin;

use App\Domain\AI\Models\AITemplate;
use App\Domain\Audit\Services\AuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertAITemplateRequest;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AITemplateController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        return view('admin.ai-templates.index', [
            'templates' => AITemplate::query()->orderByDesc('updated_at')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.ai-templates.form', [
            'aiTemplate' => new AITemplate(['status' => 'draft', 'model' => 'gpt-5', 'credit_cost' => 0]),
            'method' => 'POST',
            'action' => route('admin.ai-templates.store'),
            'modules' => config('module_registry'),
        ]);
    }

    public function store(UpsertAITemplateRequest $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $template = AITemplate::query()->create($request->validated());

        $this->auditLogger->record(
            action: 'admin.ai-template.created',
            targetType: 'ai_template',
            targetId: $template->id,
            actor: $request->user(),
            meta: ['code' => $template->code],
        );

        return redirect()->route('admin.ai-templates.edit', $template)->with('status', $flash->created('القالب'));
    }

    public function edit(AITemplate $aiTemplate): View
    {
        return view('admin.ai-templates.form', [
            'aiTemplate' => $aiTemplate,
            'method' => 'PUT',
            'action' => route('admin.ai-templates.update', $aiTemplate),
            'modules' => config('module_registry'),
        ]);
    }

    public function update(UpsertAITemplateRequest $request, AITemplate $aiTemplate, FlashMessageCatalog $flash): RedirectResponse
    {
        $aiTemplate->update($request->validated());

        $this->auditLogger->record(
            action: 'admin.ai-template.updated',
            targetType: 'ai_template',
            targetId: $aiTemplate->id,
            actor: $request->user(),
            meta: ['code' => $aiTemplate->code],
        );

        return back()->with('status', $flash->updated('القالب'));
    }

    public function destroy(AITemplate $aiTemplate, FlashMessageCatalog $flash): RedirectResponse
    {
        $code = $aiTemplate->code;
        $id = $aiTemplate->id;
        $aiTemplate->delete();

        $this->auditLogger->record(
            action: 'admin.ai-template.deleted',
            targetType: 'ai_template',
            targetId: $id,
            actor: request()->user(),
            meta: ['code' => $code],
        );

        return redirect()->route('admin.ai-templates.index')->with('status', $flash->deleted('القالب'));
    }
}
