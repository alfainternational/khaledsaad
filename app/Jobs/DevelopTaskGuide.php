<?php

namespace App\Jobs;

use App\Models\Task;
use App\Modules\Execution\TaskGuideDeveloper;
use App\Modules\Measurement\Models\QueryReservation;
use App\Modules\Measurement\QueryBudgetManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * تطوير دليل المهمة خارج دورة الطلب.
 *
 * كل استدعاء خارجي داخل طابور بمهلة صريحة (§١٤). المهلة ١٢٠ ثانية: طلب
 * واحد بإعادة محاولة مصححة يسع فيها مرتين، وما تجاوزها فشلٌ لا انتظار.
 *
 * `reservationId` لا الحجز نفسه: المهمة تُسلسَل إلى الطابور، وتمرير نموذج
 * Eloquent يجمّد نسخة قديمة منه. الحجز يُؤخَذ عند الطلب لا هنا — §٤.٤ تشترط
 * أن يسبق الحجزُ دخولَ الطابور، وإلا صار الرفض تعطيلًا متأخرًا لا منعًا.
 *
 * tries = 1 عمدًا: `TaskGuideDeveloper` يلتقط الفشل بنفسه ويكتب دليلًا
 * حتميًّا، فإعادة المحاولة على مستوى الطابور تصرف مرتين على نفس النتيجة.
 */
class DevelopTaskGuide implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $taskId,
        public readonly ?int $reservationId = null,
    ) {}

    public function handle(TaskGuideDeveloper $developer): void
    {
        $task = Task::find($this->taskId);

        if ($task === null) {
            return;
        }

        $developer->develop($task, $this->reservation());
    }

    /**
     * انهيار الطابور نفسه (مهلة أو ذاكرة) لا يترك المهمة معلّقة على
     * «يُطوَّر الآن» إلى الأبد، ولا يترك الحجز محبوسًا على السقف.
     */
    public function failed(\Throwable $exception): void
    {
        Task::where('id', $this->taskId)
            ->where('guide_status', Task::GUIDE_PENDING)
            ->update(['guide_status' => Task::GUIDE_NONE]);

        $reservation = $this->reservation();

        if ($reservation !== null && $reservation->status === QueryReservation::STATUS_HELD) {
            app(QueryBudgetManager::class)->release($reservation);
        }
    }

    private function reservation(): ?QueryReservation
    {
        return $this->reservationId === null
            ? null
            : QueryReservation::find($this->reservationId);
    }
}
