<?php

namespace App\Modules\Learning;

use App\Models\AiUsageRecord;
use App\Models\MarketingExerciseAttempt;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\QueryBudgetManager;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MarketingLessonAssistComposer
{
    public function __construct(
        private readonly MarketingLessonContextBuilder $contexts,
        private readonly MarketingLessonAssistant $assistant,
        private readonly QueryBudgetManager $budgets,
    ) {}

    /** @return array<string, mixed> */
    public function compose(MarketingExerciseAttempt $attempt, string $questionKey, ?string $sectionId = null): array
    {
        $attempt->loadMissing('run.workspace', 'run.project');
        $context = $this->contexts->build($attempt, $questionKey, $sectionId);
        $fingerprint = hash('sha256', json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $cacheKey = "marketing-lesson-assist:{$attempt->id}:{$fingerprint}";
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $reservation = $this->budgets->reserve(
                workspace: $attempt->run->workspace,
                queries: 1,
                purpose: 'marketing_lesson_assist',
                project: $attempt->run->project,
            );
        } catch (BudgetExhausted) {
            return $this->fallback($context, 'وصلت مساحة العمل إلى سقف المساعدة الذكية لهذا الشهر.');
        }

        $costFloor = (int) AiUsageRecord::max('id');

        try {
            $result = $this->assistant->suggest($context);
            $this->budgets->settle($reservation, $this->costSince($costFloor));
            Cache::put($cacheKey, $result, now()->addHours(12));

            return $result;
        } catch (Throwable $exception) {
            $this->budgets->release($reservation, $this->costSince($costFloor));
            report($exception);

            return $this->fallback($context, 'تعذرت المساعدة الذكية الآن، لذلك عرضنا إرشاد الدرس نفسه.');
        }
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function fallback(array $context, string $notice): array
    {
        $question = $context['question'];

        return [
            'field_help' => $question['help'],
            'example' => $question['example'],
            'why_it_fits' => 'هذا الإرشاد مرتبط بمعيار السؤال الحالي: '.$question['rubric'],
            'next_action' => 'اكتب إجابة عن هذا الحقل فقط، ثم راجعها على ضوء معيار السؤال.',
            'basis' => ['نص الدرس', 'معيار السؤال الحالي'],
            'evidence_label' => 'مرجع الدرس',
            'notice' => $notice,
        ];
    }

    private function costSince(int $floorId): float
    {
        return (float) AiUsageRecord::query()
            ->where('id', '>', $floorId)
            ->where('stage', 'marketing_lesson_assist')
            ->sum('cost_usd');
    }
}
