<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Execution\Models\ExecutionPackage;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ExecutionPackageResource;
use Illuminate\Http\Request;

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
}
