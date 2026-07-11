<?php

namespace App\Application\Execution;

use App\Domain\Execution\Models\ExecutionTask;
use Illuminate\Validation\ValidationException;

class UpdateExecutionTaskDetailsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ExecutionTask $task, array $data): ExecutionTask
    {
        $package = $task->executionPackage;

        if (! $package || in_array($package->status, ['executed', 'measuring'], true)) {
            throw ValidationException::withMessages([
                'task' => ['لا يمكن تعديل تفاصيل المهمة بعد تأكيد تنفيذ الحزمة.'],
            ]);
        }

        if ($data !== []) {
            $task->update($data);
        }

        return $task->fresh();
    }
}
