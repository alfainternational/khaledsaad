<?php

namespace App\Modules\Intake\Fitness;

use App\Models\AnswerFitness;

/**
 * حكم الكفاية على إجابة واحدة، مع أساسه.
 *
 * قيمة غير قابلة للتغيير تحمل **الدرجة وسببها معًا**. الفصل بينهما كان سيسمح
 * بعرض رقم بلا أساس، وهو ما تمنعه §١٥ صراحةً: لا تعرض رقمًا لا تعرف كيف حُسب.
 */
final class FitnessVerdict
{
    /**
     * @param  array<int, string>  $gaps  ما ينقص هذه الإجابة، بلغة المستخدم.
     * @param  array<int, string>  $basis  بنود الحساب كما سيُقرأ في الواجهة.
     */
    public function __construct(
        public readonly int $score,
        public readonly string $verdict,
        public readonly array $gaps,
        public readonly array $basis,
        public readonly string $expectation,
    ) {}

    public function isSufficient(): bool
    {
        return $this->verdict === AnswerFitness::VERDICT_SUFFICIENT;
    }

    /**
     * عبارة قصيرة تُعرض بجانب الدرجة. لا تخويف ولا مبالغة — الرقم المنخفض
     * يكفي وحده (§١٣).
     */
    public function headline(): string
    {
        return match ($this->verdict) {
            AnswerFitness::VERDICT_SUFFICIENT => 'إجابتك محددة بما يكفي للتشخيص.',
            AnswerFitness::VERDICT_PARTIAL => 'إجابتك مفيدة وتحتاج تحديدًا أكثر.',
            default => 'إجابتك عامة، وستُحسب بجزء من وزنها.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'verdict' => $this->verdict,
            'headline' => $this->headline(),
            'gaps' => $this->gaps,
            'basis' => $this->basis,
            'expectation' => $this->expectation,
            // كل مخرج استنتاجي يحمل وسمه (§٤.١، §١٣).
            'evidence_level' => 'inferred',
        ];
    }
}
