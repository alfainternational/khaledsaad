<?php

declare(strict_types=1);

namespace App\Support\Preflight;

use App\Support\Presentation\Num;

/**
 * ما يُعرض قبل السؤال الأول: التكلفة، والرصيد، وعدد الأسئلة، والوقت.
 *
 * الأربعة معًا لا ثلاثة منها: من يعرف التكلفة ولا يعرف عدد الأسئلة يبدأ
 * ولا يُكمل، ومن يعرف العدد ولا يعرف رصيده يُكمل ثم يصطدم.
 */
final class PreflightResult
{
    public function __construct(
        public readonly PreflightOutcome $outcome,
        public readonly int $cost = 0,
        public readonly int $balance = 0,
        public readonly int $questionsTotal = 0,
        public readonly int $questionsRemaining = 0,
        public readonly int $estimatedMinutes = 0,
    ) {}

    public static function unavailable(): self
    {
        return new self(PreflightOutcome::Unavailable);
    }

    public function isReady(): bool
    {
        return $this->outcome === PreflightOutcome::Ready;
    }

    /**
     * السطر الذي يقرأه المستخدم قبل أن يضغط «ابدأ».
     *
     * الأرقام تمرّ بالتمييز العربي، ولا تُلصق بأسماء مفردة.
     */
    /**
     * هل المانع منّا؟ الواجهة تسأل هذا قبل أن تكتب كلمة واحدة، لأن
     * الجواب يقلب النبرة كلها: اعتذارٌ لا مطالبة.
     */
    public function isOurFault(): bool
    {
        return $this->outcome === PreflightOutcome::ProviderUnavailable;
    }

    public function headline(): string
    {
        if ($this->outcome === PreflightOutcome::Unavailable) {
            return __('هذا التشخيص غير متاح حاليًا.');
        }

        if ($this->isOurFault()) {
            return __('التشغيل متوقف مؤقتًا لأسباب لدينا — لا تبدأ الآن كي لا يضيع وقتك.');
        }

        return __(':questions · نحو :minutes · يكلّف :cost (رصيدك: :balance)', [
            'questions' => trans_choice(
                '{0} بلا أسئلة جديدة|{1} سؤال واحد|{2} سؤالان|[3,10] :count أسئلة|[11,*] :count سؤالًا',
                $this->questionsRemaining,
                ['count' => Num::int($this->questionsRemaining)],
            ),
            'minutes' => trans_choice(
                '{1} دقيقة|{2} دقيقتين|[3,10] :count دقائق|[11,*] :count دقيقة',
                $this->estimatedMinutes,
                ['count' => Num::int($this->estimatedMinutes)],
            ),
            'cost' => Num::credits($this->cost),
            'balance' => Num::credits($this->balance),
        ]);
    }

    /**
     * ما ينقص من الرصيد — يُعرض بدل «رصيدك غير كافٍ» المجرّدة.
     */
    public function shortfall(): int
    {
        return max(0, $this->cost - $this->balance);
    }

    /**
     * كم سؤالًا وفّرته قاعدة إجابات المشروع — أثرٌ يستحق أن يُرى.
     */
    public function questionsSaved(): int
    {
        return max(0, $this->questionsTotal - $this->questionsRemaining);
    }
}
