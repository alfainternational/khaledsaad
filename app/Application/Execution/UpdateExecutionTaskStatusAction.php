<?php

namespace App\Application\Execution;

use App\Domain\Execution\Models\ExecutionTask;
use Illuminate\Validation\ValidationException;

class UpdateExecutionTaskStatusAction
{
    private const EXECUTABLE_PACKAGE_STATUSES = ['in_progress', 'executed', 'measuring'];

    public function handle(ExecutionTask $task, string $status): ExecutionTask
    {
        $package = $task->executionPackage;

        if (! $package || ! in_array($package->status, self::EXECUTABLE_PACKAGE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تحديث مهام التنفيذ قبل اعتماد الحزمة وبدء التنفيذ.'],
            ]);
        }

        $task->update(['status' => $status]);

        return $task->fresh();
    }
}
