<?php

namespace App\Jobs;

use App\Domain\Intelligence\Models\DiagnosisCase;
use App\Support\Intelligence\GuestDiagnosisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunGuestDiagnosisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public readonly int $diagnosisCaseId,
    ) {}

    public function handle(GuestDiagnosisService $service): void
    {
        $case = DiagnosisCase::query()->find($this->diagnosisCaseId);
        if (! $case || $case->status === 'ready' || $case->status === 'converted') {
            return;
        }

        $case->update(['status' => 'analyzing']);

        try {
            $result = $service->analyze($case);

            $case->update([
                'status' => 'ready',
                'executive_score' => $result['executive_score'],
                'integrity_status' => $result['integrity_status'],
                'report_json' => [
                    'report' => $result['report'],
                    'partial' => $result['partial'],
                ],
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            $case->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ]);
        }
    }
}
