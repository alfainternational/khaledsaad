<?php

namespace App\Application\Execution;

use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Execution\Models\ExecutionReport;
use Illuminate\Validation\ValidationException;

class CreateExecutionReportAction
{
    private const REPORTABLE_PACKAGE_STATUSES = ['in_progress', 'executed', 'measuring'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(ExecutionPackage $package, array $data): ExecutionReport
    {
        if (! in_array($package->status, self::REPORTABLE_PACKAGE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'phase' => ['لا يمكن تسجيل تقرير قياس قبل اعتماد الحزمة وبدء التنفيذ.'],
            ]);
        }

        return $package->reports()->create([
            'phase' => $data['phase'],
            'progress' => $data['progress'],
            'notes_json' => $data['notes_json'] ?? [],
            'metrics_json' => $data['metrics_json'] ?? [],
        ]);
    }
}
