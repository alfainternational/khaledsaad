<?php

namespace App\Modules\AiReadiness\Jobs;

use App\Models\Project;
use App\Modules\AiReadiness\AnswerPresenceCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * دورة استطلاع في الطابور (§٤.١: كل عملية خارجية داخل Job).
 *
 * **الحجز يقع داخل الجامع لا هنا** رغم أن §٩ تقول «قبل إدخال المهمة إلى
 * الطابور». السبب أن الجامع هو من يعرف عدد الأسئلة الفعلي بعد قراءة سياق
 * النشاط، والحجز برقم مخمَّن ثم تصحيحه يخلق نافذة يتجاوز فيها التزامنُ
 * السقف. ما تحرسه §٩ فعلًا هو ألّا يقع استدعاء بلا حجز — وهذا مضمون: الجامع
 * يحجز قبل أول نداء ويرفع `BudgetExhausted` قبل إنشاء أي دورة.
 *
 * المهمة تفشل بهدوء عند نفاد الميزانية: الرفض حالة متوقّعة لا عطل يستحق
 * إعادة محاولة — إعادتها لن تُنشئ ميزانية جديدة.
 */
class ProbeAnswerPresence implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** محاولة واحدة: نفاد الميزانية لا يُعالَج بالتكرار. */
    public int $tries = 1;

    /** خمس دقائق تكفي لخمسة أسئلة × ثلاث محاولات بمهلة كل نداء. */
    public int $timeout = 300;

    public function __construct(
        public readonly int $projectId,
        public readonly int $questionCount = 5,
    ) {}

    public function handle(AnswerPresenceCollector $collector): void
    {
        $project = Project::with(['profile', 'workspace'])->find($this->projectId);

        if ($project === null) {
            return;
        }

        $collector->collect($project, $this->questionCount);
    }
}
