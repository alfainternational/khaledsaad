<?php

namespace App\Modules\Intake\Assist;

use App\Models\AiUsageRecord;
use App\Models\Project;
use App\Models\QuestionAssist;
use App\Modules\Intake\Assist\Contracts\AssistEngine;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\QueryBudgetManager;
use Throwable;

/**
 * الحصول على دليل ومقترحات سؤال: من المخزون إن صحّ، وإلا بحجز ثم توليد.
 *
 * ثلاث مسؤوليات لا تنفصل عمليًّا:
 *
 *   ١) **إعادة الاستعمال.** الدليل يُطلب عند كل عرض للسؤال، وصاحب النشاط يرجع
 *      إلى الاستمارة مرات. توليدٌ عند كل عرض يضاعف الفاتورة على معلومة لم
 *      تتغيّر. البصمة هي الفاصل: نفس السياق ⇒ نفس المخرج بلا تكلفة.
 *
 *   ٢) **الحجز قبل الاستدعاء** (§٤.٤). لا استعلام واحد خارج الميزانية، والحجز
 *      يسبق العمل لا يتبعه — وإلا صار الرفض تعطيلًا متأخرًا بعد أن دُفع الثمن.
 *
 *   ٣) **الفشل لا يُعطّل السؤال.** المساعدة رفاهية على السؤال لا شرط للإجابة
 *      عنه: نفاد السقف أو عطل المزوّد يعيد مخرجًا فارغًا فيختفي الزر، ولا يمنع
 *      المستخدم من الكتابة. هذا هو الفرق بين قدرةٍ تساعد وقدرةٍ تحتجز.
 */
class AssistComposer
{
    /** موضع واحد لكل توليد: استدعاء واحد لكل سؤال. */
    private const PLACES_PER_COMPOSE = 1;

    public function __construct(
        private readonly AssistEngine $engine,
        private readonly AssistContextBuilder $contexts,
        private readonly QueryBudgetManager $budgets,
    ) {}

    /**
     * المخزون وحده، بلا توليد ولا تكلفة.
     *
     * تستدعيه الشاشات عند العرض الأول: لا يُطلق استعلامًا مدفوعًا لأن المستخدم
     * فتح صفحة. التوليد يبدأ بطلبه الصريح.
     */
    public function cached(Project $project, QuestionDescriptor $question): ?AssistDraft
    {
        $stored = $this->stored($project, $question);

        if ($stored === null) {
            return null;
        }

        $context = $this->contexts->build($project);

        return $stored->matches($this->contexts->fingerprint($context, $question))
            ? $this->toDraft($stored)
            : null;
    }

    /**
     * المخزون إن صحّ، وإلا توليد جديد.
     */
    public function compose(Project $project, QuestionDescriptor $question): AssistDraft
    {
        $context = $this->contexts->build($project);
        $fingerprint = $this->contexts->fingerprint($context, $question);
        $stored = $this->stored($project, $question);

        if ($stored !== null && $stored->matches($fingerprint)) {
            return $this->toDraft($stored);
        }

        try {
            $reservation = $this->budgets->reserve(
                workspace: $project->workspace,
                queries: self::PLACES_PER_COMPOSE,
                purpose: 'question_assist',
                project: $project,
            );
        } catch (BudgetExhausted) {
            /*
             * السقف نفد: يُعاد المخزون القديم إن وُجد ولو تغيّر السياق. دليل
             * عمره أسبوع أنفع من لا شيء، والفارق يُعلن للمستخدم في الواجهة
             * بتاريخ التوليد لا يُخفى (§٤.٣).
             */
            return $stored !== null ? $this->toDraft($stored) : AssistDraft::none();
        }

        /*
         * علامة على سجلات التكلفة قبل الاستدعاء: `StructuredRunner` هو من يكتب
         * `ai_usage_records` ولا يعيد التكلفة لمستدعيه. الفرق بين ما قبل وما بعد
         * هو تكلفة هذا الاستعلام بعينه، بما فيها محاولات إعادة التحقق من المخطط
         * — وهي التي تُسوَّى على السقف. لو سُوّي بصفر لصار الحجز رقمًا بلا فاتورة.
         */
        $costFloor = (int) AiUsageRecord::max('id');

        try {
            $draft = $this->engine->compose($question, $context);
        } catch (Throwable $exception) {
            $this->budgets->release($reservation, costUsd: $this->costSince($costFloor));

            report($exception);

            return $stored !== null ? $this->toDraft($stored) : AssistDraft::none();
        }

        $cost = $this->costSince($costFloor);
        $this->budgets->settle($reservation, costUsd: $cost);

        if ($draft->isEmpty()) {
            return $stored !== null ? $this->toDraft($stored) : AssistDraft::none();
        }

        QuestionAssist::updateOrCreate(
            [
                'project_id' => $project->id,
                'surface' => $question->surface,
                'question_key' => $question->questionKey,
            ],
            [
                'query_reservation_id' => $reservation->id,
                'context_hash' => $fingerprint,
                'guide' => $draft->guide,
                'suggestions' => $draft->suggestions,
                'recommended_value' => $draft->recommendedValue,
                'recommendation_reason' => $draft->recommendationReason,
                'basis' => $draft->basis,
                'evidence_level' => 'inferred',
                'provider' => $this->engine->name(),
                'model' => (string) config('ai.'.config('ai.default').'.tiers.standard'),
                'cost_usd' => $cost,
                'generated_at' => now(),
            ],
        );

        return $draft;
    }

    private function stored(Project $project, QuestionDescriptor $question): ?QuestionAssist
    {
        return QuestionAssist::query()
            ->where('project_id', $project->id)
            ->where('surface', $question->surface)
            ->where('question_key', $question->questionKey)
            ->first();
    }

    private function toDraft(QuestionAssist $assist): AssistDraft
    {
        return new AssistDraft(
            guide: (string) $assist->guide,
            suggestions: $assist->suggestions ?? [],
            recommendedValue: $assist->recommended_value,
            recommendationReason: $assist->recommendation_reason,
            basis: $assist->basis ?? [],
        );
    }

    private function costSince(int $floorId): float
    {
        return (float) AiUsageRecord::where('id', '>', $floorId)
            ->where('stage', 'question_assist')
            ->sum('cost_usd');
    }
}
