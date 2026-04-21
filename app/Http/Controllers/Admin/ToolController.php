<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Tool\Models\Tool;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertToolRequest;
use App\Support\Tooling\ToolBlueprintCatalog;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ToolController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        return view('admin.tools.index', [
            'tools' => Tool::query()->orderBy('stage')->orderBy('sort_order')->paginate(15),
        ]);
    }

    public function create(): View
    {
        $tool = new Tool(['status' => 'draft', 'stage' => 1, 'sort_order' => 0]);

        return view('admin.tools.form', [
            'tool' => $tool,
            'method' => 'POST',
            'action' => route('admin.tools.store'),
            'modules' => config('module_registry'),
            'blueprintPreview' => null,
        ]);
    }

    public function store(UpsertToolRequest $request, FlashMessageCatalog $flash): RedirectResponse
    {
        $tool = Tool::query()->create($request->validated());

        $this->auditLogger->record(
            action: 'admin.tool.created',
            targetType: 'tool',
            targetId: $tool->id,
            actor: $request->user(),
            meta: ['code' => $tool->code],
        );

        return redirect()->route('admin.tools.edit', $tool)->with('status', $flash->created('الأداة'));
    }

    public function edit(Tool $tool, ToolBlueprintCatalog $blueprints): View
    {
        $blueprintPreview = $blueprints->for($tool);
        $blueprintFound = $tool->code === 'diagnosis'
            ? true
            : $blueprintPreview['result_label'] !== 'مخرج '.$tool->code;

        return view('admin.tools.form', [
            'tool' => $tool,
            'method' => 'PUT',
            'action' => route('admin.tools.update', $tool),
            'modules' => config('module_registry'),
            'blueprintPreview' => $blueprintPreview,
            'blueprintFound' => $blueprintFound,
        ]);
    }

    public function update(UpsertToolRequest $request, Tool $tool, FlashMessageCatalog $flash): RedirectResponse
    {
        $tool->update($request->validated());

        $this->auditLogger->record(
            action: 'admin.tool.updated',
            targetType: 'tool',
            targetId: $tool->id,
            actor: $request->user(),
            meta: ['code' => $tool->code],
        );

        return back()->with('status', $flash->updated('الأداة'));
    }

    public function destroy(Tool $tool, FlashMessageCatalog $flash): RedirectResponse
    {
        $code = $tool->code;
        $id = $tool->id;
        $tool->delete();

        $this->auditLogger->record(
            action: 'admin.tool.deleted',
            targetType: 'tool',
            targetId: $id,
            actor: request()->user(),
            meta: ['code' => $code],
        );

        return redirect()->route('admin.tools.index')->with('status', $flash->deleted('الأداة'));
    }
}
