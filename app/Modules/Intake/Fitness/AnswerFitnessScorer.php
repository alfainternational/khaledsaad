<?php

namespace App\Modules\Intake\Fitness;

use App\Models\AnswerFitness;
use App\Models\Project;
use App\Modules\Shared\Text\ArabicText;

/**
 * قياس كفاية إجابة مفتوحة — حتميًّا، بلا نموذج لغوي، بلا شبكة.
 *
 * **لماذا هذا موجود:** كان `presentFactor` في `AxisScorer` يعطي 1.0 لأي نصّ غير
 * فارغ. أي أن «الجميع» تحصل على نفس درجة «أصحاب مطاعم صغيرة في الرياض يعانون من
 * ضعف الطلب في غير أوقات الذروة». النتيجة أن المحور ٢ (فهم الجمهور) كان يقيس
 * **أن المستخدم كتب شيئًا** لا **جودة تعريفه لعميله** — فيخرج التقرير مطمئنًا
 * على أضعف ما عنده، ويُبنى عليه كل ما بعده.
 *
 * حتميّ عن قصد لا عن اقتصاد: الدرجة تدخل حساب المحور، ودرجة يحسبها نموذج لغوي
 * تختلف بين تشغيلين بنفس المدخلات فتنهار المقارنة الزمنية — وعليها يقوم
 * التنبيه، المخرج المتكرر الوحيد (§٦، §١٤).
 *
 * مستوى الدليل `inferred` دائمًا: هذا حكم منهجي على نصّ كتبه صاحب النشاط عن
 * نفسه، ولا يرتفع بدقة معادلته (§١٥).
 */
class AnswerFitnessScorer
{
    /** فوق هذا: كافية. */
    private const SUFFICIENT_AT = 70;

    /** فوق هذا: مقبولة وتحتاج تحديدًا. */
    private const PARTIAL_AT = 45;

    /**
     * سقف ما يمنحه الطول وحده حين يطلب الحقل علامات محددة.
     *
     * الطول ليس جودة: فقرة من ثلاثين كلمة عامة أسوأ من سطر محدد. فحيث نعرف ما
     * الذي يجب أن تحمله الإجابة، لا تبلغ «كافية» بطولها وحده.
     */
    private const LENGTH_CEILING = 62;

    /**
     * وسقفه حين لا يطلب الحقل علامة بعينها.
     *
     * الفرق ضروري لا تجميلي: لو بقي السقف ٦٢ لحقلٍ توقّعه بلا علامات، لاستحال
     * أن تبلغ أي إجابة عنه «كافية» مهما أُتقنت — لأن الباقي كان يأتي من علامات
     * لا تُطلب أصلًا. سقفٌ لا يمكن بلوغه ليس قياسًا بل عقوبة دائمة.
     */
    private const OPEN_LENGTH_CEILING = 85;

    /** ما تمنحه كل فئة علامة متحققة. */
    private const MARKER_POINTS = 12;

    /**
     * الأنواع التي يُقاس فيها هذا.
     *
     * أسئلة الاختيار خارج القياس: الخيار محدود سلفًا، فـ«كفايته» صفة السؤال لا
     * الإجابة. قياسها يخلق رقمًا لا معنى له، وترشيح أفضل خيار فيها مسؤولية
     * `Assist` لا هذه الطبقة.
     *
     * @var array<int, string>
     */
    private const MEASURABLE_TYPES = ['text', 'textarea', 'long_text', 'repeater'];

    public static function measures(string $answerType): bool
    {
        return in_array($answerType, self::MEASURABLE_TYPES, true);
    }

    /**
     * قياس وحفظ. يعيد `null` لما لا يُقاس أو لما وصل فارغًا.
     */
    public function score(Project $project, string $fieldKey, mixed $value, string $answerType): ?AnswerFitness
    {
        if (! self::measures($answerType)) {
            return null;
        }

        $text = ArabicText::flatten($value);

        if (trim($text) === '') {
            return null;
        }

        $verdict = $this->evaluate($fieldKey, $text);

        return AnswerFitness::updateOrCreate(
            ['project_id' => $project->id, 'field_key' => $fieldKey],
            [
                'score' => $verdict->score,
                'verdict' => $verdict->verdict,
                'gaps' => $verdict->gaps,
                'basis' => $verdict->basis,
                'value_fingerprint' => hash('sha256', ArabicText::normalize($text)),
                'source' => AnswerFitness::SOURCE_DETERMINISTIC,
                'evidence_level' => 'inferred',
                'scored_at' => now(),
            ],
        );
    }

    /**
     * الحساب وحده، بلا حفظ — ليُستدعى من المعاينة اللحظية ومن الاختبار بمدخل ثابت.
     */
    public function evaluate(string $fieldKey, string $text): FitnessVerdict
    {
        $expectation = FieldExpectation::for($fieldKey);
        $words = ArabicText::wordCount($text);

        $score = $this->lengthScore($words, $expectation->minWords, $expectation->wants === []);
        $basis = [$this->wordBasis($words, $expectation->minWords)];
        $gaps = [];
        $met = [];

        foreach ($expectation->wants as $want) {
            if (MarkerLexicon::has($text, $want)) {
                $score += self::MARKER_POINTS;
                $met[] = MarkerLexicon::label($want);

                continue;
            }

            $gaps[] = MarkerLexicon::label($want);
        }

        if ($met !== []) {
            $basis[] = 'ذكرتَ: '.implode(' · ', $met);
        }

        if ($gaps !== []) {
            $basis[] = 'لم تذكر: '.implode(' · ', $gaps);
        }

        /*
         * الخصم على ألفاظ العموم يفرّق بين الغائب والمُبهَم. لفظ عموم في إجابة
         * قصيرة هو الإجابة كلها، وفي إجابة طويلة قد يكون حشوًا بين تفاصيل — لذا
         * الخصم متفاوت لا ثابت.
         */
        $vague = MarkerLexicon::vagueMatch($text);

        if ($vague !== null) {
            $penalty = $words <= $expectation->minWords ? 30 : 14;
            $score -= $penalty;
            $basis[] = "استعملتَ لفظًا عامًّا («{$vague}») لا يحدّد أحدًا.";
            $gaps[] = 'استبدال اللفظ العام بوصف محدد';
        }

        $score = (int) max(0, min(100, $score));

        return new FitnessVerdict(
            score: $score,
            verdict: match (true) {
                $score >= self::SUFFICIENT_AT => AnswerFitness::VERDICT_SUFFICIENT,
                $score >= self::PARTIAL_AT => AnswerFitness::VERDICT_PARTIAL,
                default => AnswerFitness::VERDICT_INSUFFICIENT,
            },
            gaps: array_values(array_unique($gaps)),
            basis: $basis,
            expectation: $expectation->sufficientAnswerLooksLike,
        );
    }

    /**
     * درجة الطول، منسوبة إلى الحد الأدنى المتوقَّع لهذا الحقل لا إلى رقم مطلق.
     *
     * «ثلاثة منافسين بأسمائهم» ست كلمات وهي إجابة كاملة، و«جمهوري» كلمة واحدة
     * وهي لا شيء. الحد المطلق كان سيظلم الأول ويُنجّي الثاني.
     */
    private function lengthScore(int $words, int $minWords, bool $withoutExpectedMarkers): int
    {
        if ($words === 0) {
            return 0;
        }

        $ceiling = $withoutExpectedMarkers ? self::OPEN_LENGTH_CEILING : self::LENGTH_CEILING;
        $ratio = $words / max(1, $minWords);

        return (int) round($ceiling * min(1.0, $ratio));
    }

    /**
     * كل رقم يُعرض معه أساسه (§١٣): «١٤ كلمة» بلا مرجع لا يقول للمستخدم شيئًا.
     */
    private function wordBasis(int $words, int $minWords): string
    {
        $verdict = $words >= $minWords ? 'وهو كافٍ' : 'وهو أقصر من الكافي';

        return "طول إجابتك {$words} كلمة، {$verdict} لهذا السؤال ({$minWords} كلمة).";
    }
}
