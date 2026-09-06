<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunToolPipeline;
use App\Models\ToolRun;
use App\Support\AI\Resilience\FallbackChainGateway;
use App\Support\AI\Resilience\SpendGuard;
use Illuminate\Console\Command;

/**
 * يعيد تشغيل ما أُجّل لعطلٍ لدينا.
 *
 * هذا الأمر هو ما يجعل `awaiting_capacity` وعدًا مُنفَّذًا لا كلمة ألطف
 * من «فشل». بدونه تبقى ستون إجابةً معلّقةً بانتظار أن يتذكّر صاحبها
 * العودة والضغط على زر — وأكثرهم لا يعود.
 */
class ResumeDeferredRuns extends Command
{
    protected $signature = 'runs:resume {--limit=25 : أقصى عدد يُعاد في الدفعة}';

    protected $description = 'يعيد التشغيلات المؤجَّلة لعطل لدينا فور عودة القدرة';

    public function handle(FallbackChainGateway $providers, SpendGuard $spend): int
    {
        // لا يُعاد شيء قبل عودة القدرة: إعادةٌ على مزوّد ما زال ساقطًا
        // تحرق محاولةً من السقف وتقرّب التشغيل من الفشل النهائي.
        if (! $providers->hasCapacity() || ! $spend->hasCapacity()) {
            $this->warn('القدرة لم تعد بعد — لا إعادة تشغيل الآن.');

            return self::SUCCESS;
        }

        $runs = ToolRun::query()
            ->where('status', ToolRun::STATUS_AWAITING_CAPACITY)
            ->where(fn ($query) => $query->whereNull('retry_after')->orWhere('retry_after', '<=', now()))
            ->orderBy('retry_after')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($runs as $run) {
            // المراحل المكتملة تبقى: الإعادة تُكمل من حيث توقّف الخط لا
            // من أوله، فلا تُعاد مراحل نجحت ولا تُدفع تكلفتها مرتين.
            $run->stages()->where('status', 'failed')->update([
                'status' => 'pending', 'error' => null, 'started_at' => null, 'completed_at' => null,
            ]);

            $run->forceFill([
                'status' => ToolRun::STATUS_QUEUED,
                'retry_after' => null,
            ])->save();

            // ولا تُبذَر المراحل من جديد: `seedStages` تعيد كل مرحلة إلى
            // `pending` بما فيها ما نجح، فتُعاد مراحل مكتملة وتُدفع
            // تكلفتها مرتين. الصفوف قائمة، والمعطوب وحده أُعيد أعلاه.
            dispatch(new RunToolPipeline($run->id));

            $this->line("أُعيد التشغيل #{$run->id}");
        }

        $this->info("أُعيد {$runs->count()} تشغيلًا مؤجَّلًا.");

        return self::SUCCESS;
    }
}
