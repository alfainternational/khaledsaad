<?php

namespace Tests\Feature;

use App\Exceptions\AIProviderException;
use App\Jobs\EvaluateMarketingExercise;
use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\Project;
use App\Models\User;
use App\Modules\Learning\MarketingExerciseEvaluator;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use App\Support\AI\AIRequest;
use App\Support\AI\StructuredRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MarketingExerciseEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluator_grades_every_input_stores_history_and_records_user_knowledge(): void
    {
        $this->fakeRunner(fn () => [
            'input_feedback' => [
                ['key' => 'customer_profile', 'score' => 85, 'comment' => 'العميل محدد.', 'suggestion' => 'أضف حجم النشاط.'],
                ['key' => 'customer_problem', 'score' => 80, 'comment' => 'المشكلة واضحة.', 'suggestion' => 'اذكر ما جربه العميل.'],
                ['key' => 'buying_trigger', 'score' => 75, 'comment' => 'الدافع مفهوم.', 'suggestion' => 'حدد وقت حدوثه.'],
            ],
            'overall_score' => 80,
            'strengths' => ['العميل والمشكلة مترابطان.'],
            'improvements' => ['أضف دليلًا من محادثة حقيقية.'],
            'next_action' => 'تحدث مع عميلين هذا الأسبوع.',
            'deliverable' => 'صاحب متجر صغير ينشر باستمرار ولا تصله طلبات، ويبحث عن حل بعد شهر من ضعف النتائج.',
        ]);
        [$project, $attempt] = $this->readyAttempt();

        app(MarketingExerciseEvaluator::class)->evaluate($attempt);

        $attempt->refresh();
        $this->assertSame(
            MarketingExerciseAttempt::STATUS_COMPLETED,
            $attempt->status,
            (string) $attempt->failure_reason,
        );
        $this->assertSame(80, $attempt->ai_score);
        $this->assertSame(86, $attempt->final_score);
        $this->assertSame(1, $attempt->revision);
        $this->assertCount(1, $attempt->reviews);
        $this->assertCount(3, $attempt->feedback['input_feedback']);
        $this->assertSame(
            EvidenceLevel::Inferred,
            $project->fresh()->brainFacts()->where('key', 'business.audience')->first()->evidence_level,
        );
    }

    public function test_provider_failure_preserves_answers_and_does_not_publish_a_fake_score(): void
    {
        $this->fakeRunner(fn () => throw new AIProviderException('test'));
        [, $attempt] = $this->readyAttempt();
        $answers = $attempt->answers;

        app(MarketingExerciseEvaluator::class)->evaluate($attempt);

        $attempt->refresh();
        $this->assertSame(MarketingExerciseAttempt::STATUS_REVIEW_FAILED, $attempt->status);
        $this->assertNull($attempt->ai_score);
        $this->assertNull($attempt->final_score);
        $this->assertSame($answers, $attempt->answers);
        $this->assertCount(0, $attempt->reviews);
    }

    public function test_evaluator_rejects_feedback_for_the_wrong_inputs(): void
    {
        $this->fakeRunner(fn () => [
            'input_feedback' => [
                ['key' => 'wrong_key', 'score' => 80, 'comment' => 'تعليق.', 'suggestion' => 'اقتراح.'],
            ],
            'overall_score' => 80,
            'strengths' => ['نقطة قوة.'],
            'improvements' => ['تحسين.'],
            'next_action' => 'خطوة تالية.',
            'deliverable' => 'مخرج قابل للاستخدام.',
        ]);
        [, $attempt] = $this->readyAttempt();

        app(MarketingExerciseEvaluator::class)->evaluate($attempt);

        $this->assertSame(MarketingExerciseAttempt::STATUS_REVIEW_FAILED, $attempt->refresh()->status);
        $this->assertNull($attempt->final_score);
    }

    public function test_a_concurrent_answer_change_is_never_paired_with_feedback_for_old_answers(): void
    {
        [, $attempt] = $this->readyAttempt();
        $newAnswers = [
            ...$attempt->answers,
            'customer_profile' => 'إجابة جديدة حُفظت بعد بدء المراجعة ويجب ألا تحصل على تقييم الإجابة القديمة.',
        ];
        $this->fakeRunner(function () use ($attempt, $newAnswers): array {
            $attempt->fresh()->forceFill([
                'answers' => $newAnswers,
                'status' => MarketingExerciseAttempt::STATUS_DRAFT,
            ])->save();

            return [
                'input_feedback' => [
                    ['key' => 'customer_profile', 'score' => 85, 'comment' => 'للإجابة القديمة.', 'suggestion' => 'اقتراح قديم.'],
                    ['key' => 'customer_problem', 'score' => 80, 'comment' => 'للإجابة القديمة.', 'suggestion' => 'اقتراح قديم.'],
                    ['key' => 'buying_trigger', 'score' => 75, 'comment' => 'للإجابة القديمة.', 'suggestion' => 'اقتراح قديم.'],
                ],
                'overall_score' => 80,
                'strengths' => ['قوة في الإجابة القديمة.'],
                'improvements' => ['تحسين للإجابة القديمة.'],
                'next_action' => 'خطوة مبنية على الإجابة القديمة.',
                'deliverable' => 'مخرج مبني على الإجابة القديمة ولا يجب حفظه مع الإجابة الجديدة.',
            ];
        });

        app(MarketingExerciseEvaluator::class)->evaluate($attempt);

        $attempt->refresh();
        $this->assertSame(MarketingExerciseAttempt::STATUS_DRAFT, $attempt->status);
        $this->assertSame($newAnswers, $attempt->answers);
        $this->assertNull($attempt->final_score);
        $this->assertCount(0, $attempt->reviews);
    }

    public function test_a_retry_can_reclaim_an_evaluation_interrupted_after_claiming(): void
    {
        $this->fakeRunner(fn () => [
            'input_feedback' => [
                ['key' => 'customer_profile', 'score' => 85, 'comment' => 'العميل محدد.', 'suggestion' => 'أضف حجم النشاط.'],
                ['key' => 'customer_problem', 'score' => 80, 'comment' => 'المشكلة واضحة.', 'suggestion' => 'اذكر ما جربه العميل.'],
                ['key' => 'buying_trigger', 'score' => 75, 'comment' => 'الدافع مفهوم.', 'suggestion' => 'حدد وقت حدوثه.'],
            ],
            'overall_score' => 80,
            'strengths' => ['العميل والمشكلة مترابطان.'],
            'improvements' => ['أضف دليلًا من محادثة حقيقية.'],
            'next_action' => 'تحدث مع عميلين هذا الأسبوع.',
            'deliverable' => 'وصف عملي للعميل المستهدف مبني على الإجابات المحفوظة في المهمة.',
        ]);
        [, $attempt] = $this->readyAttempt();
        $attempt->update([
            'status' => MarketingExerciseAttempt::STATUS_EVALUATING,
            'evaluation_token' => '48cfc581-4b89-40ba-9d7e-386eb61c156f',
            'evaluation_started_at' => now()->subMinutes(2),
        ]);

        app(MarketingExerciseEvaluator::class)->evaluate($attempt);

        $attempt->refresh();
        $this->assertSame(MarketingExerciseAttempt::STATUS_COMPLETED, $attempt->status);
        $this->assertNull($attempt->evaluation_token);
        $this->assertNull($attempt->evaluation_started_at);
        $this->assertCount(1, $attempt->reviews);
    }

    public function test_a_job_exhausting_worker_retries_releases_the_attempt_for_user_retry(): void
    {
        [, $attempt] = $this->readyAttempt();
        $attempt->update([
            'status' => MarketingExerciseAttempt::STATUS_EVALUATING,
            'evaluation_token' => '48cfc581-4b89-40ba-9d7e-386eb61c156f',
            'evaluation_started_at' => now(),
        ]);

        (new EvaluateMarketingExercise($attempt->id))->failed(new RuntimeException('worker timeout'));

        $attempt->refresh();
        $this->assertSame(MarketingExerciseAttempt::STATUS_REVIEW_FAILED, $attempt->status);
        $this->assertNull($attempt->evaluation_token);
        $this->assertNull($attempt->evaluation_started_at);
        $this->assertNotEmpty($attempt->answers);
    }

    public function test_review_context_includes_only_related_completed_answers(): void
    {
        $captured = null;
        $this->fakeRunner(function (AIRequest $request) use (&$captured): array {
            $captured = json_decode($request->messages[1]['content'], true, 512, JSON_THROW_ON_ERROR);

            return [
                'input_feedback' => [
                    ['key' => 'customer_profile', 'score' => 85, 'comment' => 'العميل محدد.', 'suggestion' => 'أضف حجم النشاط.'],
                    ['key' => 'customer_problem', 'score' => 80, 'comment' => 'المشكلة واضحة.', 'suggestion' => 'اذكر ما جربه العميل.'],
                    ['key' => 'buying_trigger', 'score' => 75, 'comment' => 'الدافع مفهوم.', 'suggestion' => 'حدد وقت حدوثه.'],
                ],
                'overall_score' => 80,
                'strengths' => ['العميل والمشكلة مترابطان.'],
                'improvements' => ['أضف دليلًا من محادثة حقيقية.'],
                'next_action' => 'تحدث مع عميلين هذا الأسبوع.',
                'deliverable' => 'وصف عملي للعميل المستهدف مبني على الإجابات المحفوظة في المهمة.',
            ];
        });
        [, $attempt] = $this->readyAttempt();
        $attempt->run->attempts()->create([
            'exercise_key' => 'core-marketing-message',
            'answers' => [
                'customer_and_problem' => 'نخاطب صاحب متجر صغير لا تتحول زياراته إلى طلبات.',
                'promise_and_difference' => 'نوضح له موضع التسرب ونقترح تجربة قصيرة.',
            ],
            'status' => MarketingExerciseAttempt::STATUS_COMPLETED,
            'final_score' => 82,
            'evaluated_at' => now()->subDay(),
        ]);

        app(MarketingExerciseEvaluator::class)->evaluate($attempt);

        $related = $captured['exercise']['related_previous_answers'] ?? [];
        $this->assertCount(1, $related);
        $this->assertSame('اكتب رسالتك التسويقية الأساسية', $related[0]['exercise_title']);
        $this->assertSame(
            'نخاطب صاحب متجر صغير لا تتحول زياراته إلى طلبات.',
            $related[0]['answers'][0]['answer'],
        );
    }

    /**
     * @return array{Project, MarketingExerciseAttempt}
     */
    private function readyAttempt(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر المراجعة']);
        $project->brainFacts()->delete();
        $run = MarketingLearningRun::startFor($project, $user);
        $attempt = $run->attempts()->create([
            'exercise_key' => 'describe-real-customer',
            'answers' => [
                'customer_profile' => 'صاحب متجر صغير ينشر أسبوعيًا ويريد طلبات أكثر من مدينته.',
                'customer_problem' => 'ينشر باستمرار لكنه لا يعرف لماذا لا تتحول المشاهدات إلى طلبات.',
                'buying_trigger' => 'بعد مرور شهر كامل دون زيادة واضحة في الطلبات يبدأ بالبحث عن مساعدة.',
            ],
            'status' => MarketingExerciseAttempt::STATUS_QUEUED,
            'completeness_score' => 100,
            'submitted_at' => now(),
        ]);

        return [$project, $attempt];
    }

    private function fakeRunner(callable $handler): void
    {
        $this->app->instance(StructuredRunner::class, new class($handler) extends StructuredRunner
        {
            public function __construct(private $handler) {}

            public function run(AIRequest $request, $toolRun = null): array
            {
                return ($this->handler)($request);
            }
        });
    }
}
