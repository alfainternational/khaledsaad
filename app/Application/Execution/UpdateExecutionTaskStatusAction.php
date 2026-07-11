<?php

namespace App\Application\Execution;

use App\Domain\Execution\Models\ExecutionTask;
use Illuminate\Validation\ValidationException;

class UpdateExecutionTaskStatusAction
{
    public function handle(ExecutionTask $task, string $status): ExecutionTask
    {
        $package = $task->executionPackage;

        if (! $package || $package->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تحديث مهام التنفيذ إلا أثناء مرحلة التنفيذ.'],
            ]);
        }

        $task->update(['status' => $status]);

        return $task->fresh();
    }
}
