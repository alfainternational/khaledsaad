<?php

namespace App\Modules\AiReadiness;

use App\Models\Project;
use App\Modules\AiReadiness\Contracts\AnswerEngine;
use App\Modules\AiReadiness\Models\PresenceProbe;
use App\Modules\AiReadiness\Models\PresenceRun;
use App\Modules\Measurement\Exceptions\BudgetExhausted;
use App\Modules\Measurement\Models\QueryReservation;
use App\Modules\Measurement\QueryBudgetManager;
use Throwable;

/**
 * استطلاع الحضور في إجابات النماذج.
 *
 * ثلاث قواعد تحكم هذا الصنف، وكلها من المواصفة لا من الهندسة:
 *
 * ١. **الحجز قبل الاستدعاء** (§٩). الميزانية تُحجز كاملةً قبل أول نداء، وما
 *    لم يُستهلك يعود. الحجز أثناء التنفيذ يعني أن نصف الاستطلاع قد نُفِّذ
 *    قبل أن يُكتشف أن لا ميزانية له.
 *
 * ٢. **ثلاث محاولات حدًّا أدنى** (§٤.٢). لا رقم من عيّنة واحدة، ولا «ظاهر /
 *    غائب» — الصيغة الصحيحة «٢ من ٥».
 *
 * ٣. **`raw_response` كاملًا دائمًا** (§١٤). التصنيف قد يتغيّر، والنص الخام هو
 *    الدليل. بلا حفظه تكون إعادة التصنيف إعادةَ دفع.
 */
class AnswerPresenceCollector
{
    public function __construct(
        private readonly AnswerEngine $engine,
        private readonly QuestionBank $questions,
        private readonly QueryBudgetManager $budgets,
        private readonly BrandMatcher $matcher,
    ) {}

    /**
     * دورة استطلاع كاملة.
     *
     * @throws BudgetExhausted
     */
    public function collect(Project $project, int $questionCount = 5, ?int $attempts = null): PresenceRun
    {
        $attempts = max($attempts ?? PresenceRun::MIN_ATTEMPTS, PresenceRun::MIN_ATTEMPTS);
        $questions = $this->questions->for($project, $questionCount);

        $reservation = $this->budgets->reserve(
            workspace: $project->workspace,
            queries: count($questions) * $attempts,
            purpose: 'answer_presence',
            project: $project,
        );

        $run = PresenceRun::create([
            'project_id' => $project->id,
            'query_reservation_id' => $reservation->id,
            'provider' => $this->engine->name(),
            'model' => $this->engine->model(),
            'questions_count' => count($questions),
            'attempts_per_question' => $attempts,
            'status' => PresenceRun::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        return $this->probeAll($run, $project, $questions, $attempts, $reservation);
    }

    /**
     * @param  array<int, array<string, string>>  $questions
     */
    private function probeAll(
        PresenceRun $run,
        Project $project,
        array $questions,
        int $attempts,
        QueryReservation $reservation,
    ): PresenceRun {
        $brand = $project->name;
        $site = $project->profile?->website;

        $cost = 0.0;
        $succeeded = 0;
        $used = 0;

        foreach ($questions as $question) {
            foreach (range(1, $attempts) as $attempt) {
                $used++;

                try {
                    $answer = $this->engine->ask($question['text']);
                    $cost += (float) $answer['cost_usd'];

                    $reading = $this->matcher->read($answer['text'], $brand, $site);

                    PresenceProbe::create([
                        'presence_run_id' => $run->id,
                        'question_key' => $question['key'],
                        'question' => $question['text'],
                        'attempt' => $attempt,
                        'brand_mentioned' => $reading['brand_mentioned'],
                        'site_cited' => $reading['site_cited'],
                        'brands_mentioned' => $reading['brands_mentioned'],
                        'citations' => $reading['citations'],
                        'raw_response' => $answer['text'],
                        'latency_ms' => (int) $answer['latency_ms'],
                        'status' => PresenceProbe::STATUS_OK,
                    ]);

                    $succeeded++;
                } catch (Throwable $exception) {
                    /*
                     * المحاولة الفاشلة تُسجَّل ولا تُهمَل: «لم تقع» معلومة
                     * مختلفة عن «وقعت ولم تُذكر فيها العلامة»، والخلط بينهما
                     * يخفض معدّل الذكر بعطلٍ في المزوّد (§١٢).
                     */
                    PresenceProbe::create([
                        'presence_run_id' => $run->id,
                        'question_key' => $question['key'],
                        'question' => $question['text'],
                        'attempt' => $attempt,
                        'raw_response' => null,
                        'status' => PresenceProbe::STATUS_FAILED,
                    ]);
                }
            }
        }

        $this->budgets->settle($reservation, costUsd: $cost, actualQueries: $used);

        $expected = count($questions) * $attempts;

        return tap($run)->update([
            'status' => match (true) {
                $succeeded === 0 => PresenceRun::STATUS_FAILED,
                $succeeded < $expected => PresenceRun::STATUS_PARTIAL,
                default => PresenceRun::STATUS_COMPLETED,
            },
            'failure_reason' => $succeeded === $expected
                ? null
                : sprintf(__('نجحت %d محاولة من %d.'), $succeeded, $expected),
            'cost_usd' => $cost,
            'completed_at' => now(),
        ]);
    }
}
