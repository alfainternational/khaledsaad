<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Execution\AdvanceExecutionPackageStatusAction;
use App\Application\Execution\CreateExecutionReportAction;
use App\Application\Execution\UpdateExecutionTaskDetailsAction;
use App\Application\Execution\UpdateExecutionTaskStatusAction;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\ExecutionReport;
use App\Domain\Execution\Models\ExecutionTask;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ExecutionPackageResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExecutionPackageController extends Controller
{
    public function show(Request $request): ExecutionPackageResource
    {
        $package = $this->resolve((string) $request->route('packagePublicId'));
        $this->authorize('view', $package->project);

        return new ExecutionPackageResource(
            $package->load(['owner', 'tasks.assignee', 'assets', 'reports', 'recommendation'])
        );
    }

    public function update(Request $request): ExecutionPackageResource
    {
        $package = $this->resolve((string) $request->route('packagePublicId'));
        $this->authorize('update', $package->project);

        if (in_array($package->status, ['executed', 'measuring'], true)) {
            throw ValidationException::withMessages([
                'package' => ['لا يمكن تعديل تفاصيل الحزمة بعد تأكيد التنفيذ.'],
            ]);
        }

        $validated = $request->validate([
            'owner_user_id' => ['sometimes', 'nullable', 'integer'],
            'owner_public_id' => ['sometimes', 'nullable', 'string'],
            'deadline' => ['sometimes', 'nullable', 'date'],
        ]);

        if (array_key_exists('owner_public_id', $validated)) {
            $validated['owner_user_id'] = $validated['owner_public_id'] === null
                ? null
                : $this->resolveWorkspaceOwnerId((string) $validated['owner_public_id']);
            unset($validated['owner_public_id']);
        }

        if (array_key_exists('owner_user_id', $validated) && $validated['owner_user_id'] !== null) {
            $this->ensureUserBelongsToWorkspace((int) $validated['owner_user_id'], 'owner_user_id');
        }

        $package->update($validated);

        return new ExecutionPackageResource($package->fresh()->load(['owner', 'tasks.assignee', 'assets', 'reports']));
    }

    public function updateStatus(
        Request $request,
        AdvanceExecutionPackageStatusAction $advanceExecutionPackageStatus,
    ): ExecutionPackageResource
    {
        $package = $this->resolve((string) $request->route('packagePublicId'));
        $this->authorize('update', $package->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionPackage::STATUSES)],
        ]);

        $package = $advanceExecutionPackageStatus->handle($package, $validated['status']);

        return new ExecutionPackageResource($package->load(['owner', 'tasks.assignee', 'assets', 'reports']));
    }

    public function storeReport(
        Request $request,
        CreateExecutionReportAction $createExecutionReport,
    ): ExecutionPackageResource
    {
        $package = $this->resolve((string) $request->route('packagePublicId'));
        $this->authorize('update', $package->project);

        $validated = $request->validate([
            'phase' => ['required', 'string', 'in:'.implode(',', ExecutionReport::PHASES)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
            'metric_name' => ['nullable', 'string', 'max:120'],
            'metric_value' => ['nullable', 'string', 'max:120'],
            'notes' => ['sometimes', 'array'],
            'metrics' => ['sometimes', 'array'],
        ]);

        $notes = $validated['notes'] ?? [];
        if (filled($validated['note'] ?? null)) {
            $notes['summary'] = $validated['note'];
        }

        $metrics = $validated['metrics'] ?? [];
        if (filled($validated['metric_name'] ?? null) || filled($validated['metric_value'] ?? null)) {
            $metrics[] = [
                'name' => $validated['metric_name'] ?? '',
                'value' => $validated['metric_value'] ?? '',
            ];
        }

        $createExecutionReport->handle($package, [
            'phase' => $validated['phase'],
            'progress' => $validated['progress'],
            'notes_json' => $notes,
            'metrics_json' => $metrics,
        ]);

        return new ExecutionPackageResource($package->fresh()->load(['owner', 'tasks.assignee', 'assets', 'reports']));
    }

    public function updateTaskStatus(
        Request $request,
        UpdateExecutionTaskStatusAction $updateExecutionTaskStatus,
    ): ExecutionPackageResource
    {
        $task = $this->resolveTask((string) $request->route('taskPublicId'));
        $package = $task->executionPackage;
        $this->authorize('update', $package->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionTask::STATUSES)],
        ]);

        $updateExecutionTaskStatus->handle($task, $validated['status']);

        return new ExecutionPackageResource($package->fresh()->load(['owner', 'tasks.assignee', 'assets', 'reports']));
    }

    public function updateTask(
        Request $request,
        UpdateExecutionTaskDetailsAction $updateExecutionTaskDetails,
        UpdateExecutionTaskStatusAction $updateExecutionTaskStatus,
    ): ExecutionPackageResource
    {
        $task = $this->resolveTask((string) $request->route('taskPublicId'));
        $package = $task->executionPackage;
        $this->authorize('update', $package->project);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:'.implode(',', ExecutionTask::STATUSES)],
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
            'assignee_public_id' => ['sometimes', 'nullable', 'string'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);

        if (array_key_exists('assignee_public_id', $validated)) {
            $validated['assigned_to'] = $validated['assignee_public_id'] === null
                ? null
                : $this->resolveWorkspaceAssigneeId((string) $validated['assignee_public_id']);
            unset($validated['assignee_public_id']);
        }

        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== null) {
            $this->ensureUserBelongsToWorkspace((int) $validated['assigned_to'], 'assigned_to');
        }

        if (array_key_exists('status', $validated)) {
            $updateExecutionTaskStatus->handle($task, (string) $validated['status']);
            unset($validated['status']);
        }

        $updateExecutionTaskDetails->handle($task, $validated);

        return new ExecutionPackageResource($package->fresh()->load(['owner', 'tasks.assignee', 'assets', 'reports']));
    }

    /**
     * يحل الحزمة ضمن مساحة العمل الحالية (عزل صارم).
     */
    private function resolve(string $publicId): ExecutionPackage
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        return ExecutionPackage::query()
            ->where('workspace_id', $workspace->id)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }

    private function resolveTask(string $publicId): ExecutionTask
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        return ExecutionTask::query()
            ->where('public_id', $publicId)
            ->whereHas('executionPackage', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->with('executionPackage.project')
            ->firstOrFail();
    }

    private function ensureUserBelongsToWorkspace(int $userId, string $field): void
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $isMember = $workspace->members()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                $field => ['المستخدم المحدد ليس عضواً نشطاً في مساحة العمل.'],
            ]);
        }
    }

    private function resolveWorkspaceOwnerId(string $publicId): int
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $userId = User::query()
            ->where('public_id', $publicId)
            ->whereHas('workspaceMemberships', fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active'))
            ->value('id');

        if ($userId === null) {
            throw ValidationException::withMessages([
                'owner_public_id' => ['المستخدم المحدد ليس عضواً نشطاً في مساحة العمل.'],
            ]);
        }

        return (int) $userId;
    }

    private function resolveWorkspaceAssigneeId(string $publicId): int
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $userId = User::query()
            ->where('public_id', $publicId)
            ->whereHas('workspaceMemberships', fn ($query) => $query
                ->where('workspace_id', $workspace->id)
                ->where('status', 'active'))
            ->value('id');

        if ($userId === null) {
            throw ValidationException::withMessages([
                'assignee_public_id' => ['المستخدم المحدد ليس عضواً نشطاً في مساحة العمل.'],
            ]);
        }

        return (int) $userId;
    }
}
