<?php

namespace App\Http\Controllers\Web;

use App\Application\Execution\AdvanceExecutionPackageStatusAction;
use App\Application\Execution\CreateExecutionReportAction;
use App\Application\Execution\UpdateExecutionTaskDetailsAction;
use App\Application\Execution\UpdateExecutionTaskStatusAction;
use App\Domain\AI\Models\AITemplate;
use App\Domain\Entitlement\Services\EntitlementResolver;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\ExecutionReport;
use App\Domain\Execution\Models\ExecutionTask;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\InteractsWithWorkspaceContext;
use App\Support\Agency\WhiteLabelResolver;
use App\Support\Ui\FlashMessageCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExecutionPackageController extends Controller
{
    use InteractsWithWorkspaceContext;

    public function show(
        Request $request,
        ExecutionPackage $executionPackage,
        WhiteLabelResolver $whiteLabel,
        EntitlementResolver $entitlements,
    ): View {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);

        $executionPackage->load(['owner', 'tasks.assignee', 'assets', 'reports', 'recommendation', 'project']);
        $this->authorize('view', $executionPackage->project);

        return view('app.execution-packages.show', [
            'package' => $executionPackage,
            'brand' => $whiteLabel->for($workspace),
            'studioTemplates' => $this->studioTemplatesFor($executionPackage),
            'studioBrief' => $this->studioBriefFor($executionPackage),
            'studioEnabled' => $entitlements->boolean('modules.ai_studio', $workspace),
            'activeMembers' => $workspace->members()
                ->with('user')
                ->where('status', 'active')
                ->get()
                ->sortBy(fn ($member) => $member->user?->name ?? ''),
        ]);
    }

    public function updateStatus(
        Request $request,
        ExecutionPackage $executionPackage,
        FlashMessageCatalog $flash,
        AdvanceExecutionPackageStatusAction $advanceExecutionPackageStatus,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionPackage::STATUSES)],
        ]);

        $advanceExecutionPackageStatus->handle($executionPackage, $validated['status']);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->statusUpdated('حزمة التنفيذ'));
    }

    public function updateDetails(
        Request $request,
        ExecutionPackage $executionPackage,
        FlashMessageCatalog $flash,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        $this->authorize('update', $executionPackage->project);

        if (in_array($executionPackage->status, ['executed', 'measuring'], true)) {
            throw ValidationException::withMessages([
                'package' => ['لا يمكن تعديل تفاصيل الحزمة بعد تأكيد التنفيذ.'],
            ]);
        }

        $validated = $request->validate([
            'owner_user_id' => ['nullable', 'integer'],
            'deadline' => ['nullable', 'date'],
        ]);

        if (($validated['owner_user_id'] ?? null) !== null) {
            $isMember = $workspace->members()
                ->where('user_id', (int) $validated['owner_user_id'])
                ->where('status', 'active')
                ->exists();

            if (! $isMember) {
                throw ValidationException::withMessages([
                    'owner_user_id' => ['المستخدم المحدد ليس عضواً نشطاً في مساحة العمل.'],
                ]);
            }
        }

        $executionPackage->update($validated);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->updated('تفاصيل حزمة التنفيذ'));
    }

    public function updateTaskStatus(
        Request $request,
        ExecutionPackage $executionPackage,
        ExecutionTask $executionTask,
        FlashMessageCatalog $flash,
        UpdateExecutionTaskStatusAction $updateExecutionTaskStatus,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        abort_unless($executionTask->execution_package_id === $executionPackage->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionTask::STATUSES)],
        ]);

        $updateExecutionTaskStatus->handle($executionTask, $validated['status']);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->statusUpdated('مهمة التنفيذ'));
    }

    public function updateTaskDetails(
        Request $request,
        ExecutionPackage $executionPackage,
        ExecutionTask $executionTask,
        FlashMessageCatalog $flash,
        UpdateExecutionTaskDetailsAction $updateExecutionTaskDetails,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        abort_unless($executionTask->execution_package_id === $executionPackage->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer'],
            'due_date' => ['nullable', 'date'],
        ]);

        if (($validated['assigned_to'] ?? null) !== null) {
            $isMember = $workspace->members()
                ->where('user_id', (int) $validated['assigned_to'])
                ->where('status', 'active')
                ->exists();

            if (! $isMember) {
                throw ValidationException::withMessages([
                    'assigned_to' => ['المستخدم المحدد ليس عضواً نشطاً في مساحة العمل.'],
                ]);
            }
        }

        $updateExecutionTaskDetails->handle($executionTask, $validated);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->updated('تفاصيل مهمة التنفيذ'));
    }

    public function storeReport(
        Request $request,
        ExecutionPackage $executionPackage,
        FlashMessageCatalog $flash,
        CreateExecutionReportAction $createExecutionReport,
    ): RedirectResponse {
        $workspace = $this->currentWorkspace($request);
        abort_unless($executionPackage->workspace_id === $workspace->id, 404);
        $this->authorize('update', $executionPackage->project);

        $validated = $request->validate([
            'phase' => ['required', 'string', 'in:'.implode(',', ExecutionReport::PHASES)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'metric_name' => ['nullable', 'string', 'max:120'],
            'metric_value' => ['nullable', 'string', 'max:120'],
        ]);

        $notes = filled($validated['note'] ?? null) ? ['summary' => $validated['note']] : [];
        $metrics = filled($validated['metric_name'] ?? null) || filled($validated['metric_value'] ?? null)
            ? [[
                'name' => $validated['metric_name'] ?? '',
                'value' => $validated['metric_value'] ?? '',
            ]]
            : [];

        $createExecutionReport->handle($executionPackage, [
            'phase' => $validated['phase'],
            'progress' => $validated['progress'],
            'notes_json' => $notes,
            'metrics_json' => $metrics,
        ]);

        return redirect()
            ->route('execution-packages.show', $executionPackage)
            ->with('status', $flash->created('تقرير القياس'));
    }

    private function studioTemplatesFor(ExecutionPackage $package)
    {
        $assetTypes = $package->assets->pluck('type')->all();
        $preferred = in_array('dev_brief', $assetTypes, true)
            ? ['landing-headlines', 'social-ad', 'whatsapp-followup']
            : ['social-ad', 'content-calendar', 'whatsapp-followup', 'landing-headlines'];

        return AITemplate::query()
            ->where('status', 'published')
            ->whereIn('code', $preferred)
            ->get()
            ->sortBy(function (AITemplate $template) use ($preferred): int {
                $position = array_search($template->code, $preferred, true);

                return $position === false ? 99 : $position;
            })
            ->values();
    }

    private function studioBriefFor(ExecutionPackage $package): string
    {
        $asset = $package->assets->first();

        return trim(implode("\n", array_filter([
            'حوّل حزمة التنفيذ التالية إلى مخرج جاهز للتسليم.',
            'المشروع: '.$package->project?->name,
            'عنوان الحزمة: '.$package->title,
            $package->problem ? 'المشكلة: '.$package->problem : null,
            $package->evidence ? 'الدليل: '.$package->evidence : null,
            $package->decision ? 'القرار المطلوب: '.$package->decision : null,
            $asset?->body ? "موجز المخرج الحالي:\n".$asset->body : null,
            $package->measurement_plan ? 'خطة القياس: '.$package->measurement_plan : null,
        ])));
    }
}
