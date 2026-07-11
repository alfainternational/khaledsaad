<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\ExecutionTask;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ExecutionPackageResource;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExecutionPackageController extends Controller
{
    public function show(Request $request): ExecutionPackageResource
    {
        $package = $this->resolve((string) $request->route('packagePublicId'));
        $this->authorize('view', $package->project);

        return new ExecutionPackageResource(
            $package->load(['tasks', 'assets', 'recommendation'])
        );
    }

    public function updateStatus(Request $request): ExecutionPackageResource
    {
        $package = $this->resolve((string) $request->route('packagePublicId'));
        $this->authorize('update', $package->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionPackage::STATUSES)],
        ]);

        $package->update(['status' => $validated['status']]);

        return new ExecutionPackageResource($package->load(['tasks', 'assets']));
    }

    public function updateTaskStatus(Request $request): ExecutionPackageResource
    {
        $task = $this->resolveTask((string) $request->route('taskPublicId'));
        $package = $task->executionPackage;
        $this->authorize('update', $package->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', ExecutionTask::STATUSES)],
        ]);

        $task->update(['status' => $validated['status']]);

        return new ExecutionPackageResource($package->fresh()->load(['tasks', 'assets']));
    }

    public function updateTask(Request $request): ExecutionPackageResource
    {
        $task = $this->resolveTask((string) $request->route('taskPublicId'));
        $package = $task->executionPackage;
        $this->authorize('update', $package->project);

        $validated = $request->validate([
            'assigned_to' => ['sometimes', 'nullable', 'integer'],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ]);

        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] !== null) {
            $this->ensureAssigneeBelongsToWorkspace((int) $validated['assigned_to']);
        }

        $task->update($validated);

        return new ExecutionPackageResource($package->fresh()->load(['tasks', 'assets']));
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

    private function ensureAssigneeBelongsToWorkspace(int $userId): void
    {
        /** @var \App\Domain\Workspace\Models\Workspace $workspace */
        $workspace = app('currentWorkspace');

        $isMember = $workspace->members()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $isMember) {
            throw ValidationException::withMessages([
                'assigned_to' => ['المستخدم المحدد ليس عضواً نشطاً في مساحة العمل.'],
            ]);
        }
    }
}
