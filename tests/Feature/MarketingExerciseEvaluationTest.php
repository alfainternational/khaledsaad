<?php

namespace Tests\Feature;

use App\Exceptions\AIProviderException;
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
