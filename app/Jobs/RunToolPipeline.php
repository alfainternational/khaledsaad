<?php

namespace App\Jobs;

use App\Models\ToolRun;
use App\Services\Tools\ToolRunPipeline;
use App\Support\Failures\FailureClassifier;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class RunToolPipeline implements ShouldQueue
{
    use Batchable, Queueable;

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

    /**
     * سقوط المهمة نفسها (مهلة، ذاكرة، طابور) لا يمرّ بـ `fail()` داخل خط
     * الأنابيب، فيُصنَّف هنا بالمصنّف ذاته كي لا يبقى مسارُ عطلٍ واحد
     * يتحدث بلغة مختلفة عن أخيه.
     */
    public function failed(\Throwable $exception): void
    {
        $failure = (new FailureClassifier)->classify($exception);

        ToolRun::where('id', $this->toolRunId)->update([
            'status' => ToolRun::STATUS_FAILED,
            'failure_reason' => $failure->message,
            'failure_kind' => $failure->kind->value,
            'failure_code' => $failure->code,
            'failure_detail' => Str::limit($exception->getMessage(), 2000),
            'completed_at' => now(),
        ]);
    }
}
