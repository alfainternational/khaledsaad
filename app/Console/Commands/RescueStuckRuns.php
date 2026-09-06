<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ToolRun;
use App\Support\Failures\FailureKind;
use Illuminate\Console\Command;

/**
 * إنقاذ التشغيلات التي سقطت قبل أن يوجد التصنيف.
 *
 * **هذه ليست مهمة صيانة.** في قاعدة الإنتاج الآن تشغيلاتٌ بحالة `failed`
 * تحمل إجابات مستخدمين حقيقيين — وأثمنها من سقط بعطل المزوّد، أي بعطلٍ
 * لنا. هؤلاء بذلوا مجهودهم كاملًا ولم ينالوا شيئًا، ولا يزالون.
 *
 * ما يُرحَّل: ما كان عطله منّا (أو مجهول التصنيف، ونفترض براءة المستخدم).
 * وما لا يُرحَّل: ما كان حدًّا يخصّه — رصيد أو خطة — فإعادته بلا رصيد
 * تحرق محاولةً وتُنتج الفشل نفسه.
 */
class RescueStuckRuns extends Command
{
    protected $signature = 'runs:rescue
        {--dry : يعرض ما سيُرحَّل دون تغيير شيء}
        {--limit=0 : حدّ أقصى للدفعة، صفر يعني الكل}';

    protected $description = 'يرحّل التشغيلات العالقة بحالة failed إلى انتظار القدرة لتُعاد تلقائيًّا';

    public function handle(): int
    {
        $query = ToolRun::query()
            ->where('status', ToolRun::STATUS_FAILED)
            // حدُّ المستخدم يبقى كما هو: إعادته بلا رصيد تُنتج الفشل نفسه.
            ->where(fn ($q) => $q->whereNull('failure_kind')
                ->orWhere('failure_kind', FailureKind::Ours->value))
            ->orderBy('id');

        if ((int) $this->option('limit') > 0) {
            $query->limit((int) $this->option('limit'));
        }

        $runs = $query->get();

        if ($runs->isEmpty()) {
            $this->info('لا تشغيلات عالقة تحتاج إنقاذًا.');

            return self::SUCCESS;
        }

        $this->line("عُثر على {$runs->count()} تشغيلًا عالقًا.");

        if ($this->option('dry')) {
            foreach ($runs as $run) {
                $answers = $run->answers()->count();
                $this->line("#{$run->id} · {$answers} إجابة · {$run->created_at?->toDateString()}");
            }

            $this->warn('عرضٌ فقط — لم يتغير شيء. أعِد الأمر بلا ‎--dry‎ للتنفيذ.');

            return self::SUCCESS;
        }

        foreach ($runs as $run) {
            $run->forceFill([
                'status' => ToolRun::STATUS_AWAITING_CAPACITY,
                'failure_kind' => FailureKind::Ours->value,
                'failure_code' => 'provider_unavailable',
                'failure_reason' => __('إجاباتك محفوظة بالكامل، ولم يُخصم من رصيدك شيء. سنشغّل التحليل تلقائيًا فور عودة الخدمة ونُشعرك.'),
                // فورًا: هؤلاء انتظروا ما يكفي.
                'retry_after' => now(),
                // العدّاد يبدأ من الصفر: محاولاتهم السابقة وقعت قبل وجود
                // السلسلة الاحتياطية، فليست دليلًا على أن الإعادة ستفشل.
                'auto_attempts' => 0,
                'completed_at' => null,
            ])->save();
        }

        $this->info("رُحِّل {$runs->count()} تشغيلًا. شغّل ‎runs:resume‎ لإعادتها.");

        return self::SUCCESS;
    }
}
