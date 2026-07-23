<?php

namespace App\Jobs;

use App\Models\ToolRun;
use App\Services\Tools\ToolRunPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunToolPipeline implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $toolRunId) {}

    public function handle(ToolRunPipeline $pipeline): void
    {
        $run = ToolRun::find($this->toolRunId);

        if ($run === null || $run->isTerminal()) {
            return;
        }

        $pipeline->handle($run);
    }

    public function failed(\Throwable $exception): void
    {
        ToolRun::where('id', $this->toolRunId)->update([
            'status' => ToolRun::STATUS_FAILED,
            'failure_reason' => 'توقف التشغيل بشكل غير متوقع. إجاباتك محفوظة ويمكن إعادة المحاولة.',
            'completed_at' => now(),
        ]);
    }
}
