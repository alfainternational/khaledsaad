<?php

namespace App\Application\Execution;

use App\Domain\Execution\Models\ExecutionPackage;
use Illuminate\Validation\ValidationException;

class AdvanceExecutionPackageStatusAction
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $allowedTransitions = [
        'approved' => ['in_progress'],
        'in_progress' => ['executed'],
        'executed' => ['measuring'],
    ];

    public function handle(ExecutionPackage $package, string $nextStatus): ExecutionPackage
    {
        $allowed = $this->allowedTransitions[$package->status] ?? [];

        if (! in_array($nextStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تغيير حالة حزمة التنفيذ إلى هذه المرحلة قبل اكتمال خطوة الاعتماد المناسبة.'],
            ]);
        }

        $package->update(['status' => $nextStatus]);

        return $package->fresh();
    }
}
