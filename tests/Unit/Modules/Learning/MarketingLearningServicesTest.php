<?php

namespace Tests\Unit\Modules\Learning;

use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Learning\MarketingAnswerPrefill;
use App\Modules\Learning\MarketingExerciseCompletenessScorer;
use App\Modules\Learning\MarketingLearningRecommender;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingLearningServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_completeness_requires_meaningful_text_and_valid_numbers(): void
    {
        $exercise = ['questions' => [
            ['key' => 'problem', 'type' => 'textarea', 'required' => true, 'min' => 20],
            ['key' => 'budget', 'type' => 'number', 'required' => true, 'min' => 0],
        ]];

        $result = app(MarketingExerciseCompletenessScorer::class)->score($exercise, [
            'problem' => 'قصير',
            'budget' => -1,
        ]);

        $this->assertLessThan(60, $result['score']);
        $this->assertSame(['problem', 'budget'], $result['missing']);
    }

    public function test_completeness_reaches_one_hundred_for_valid_answers(): void
    {
        $exercise = ['questions' => [
            ['key' => 'problem', 'type' => 'textarea', 'required' => true, 'min' => 20],
            ['key' => 'budget', 'type' => 'number', 'required' => true, 'min' => 0],
        ]];

        $result = app(MarketingExerciseCompletenessScorer::class)->score($exercise, [
            'problem' => 'العميل ينشر أسبوعيًا لكنه لا يحصل على طلبات واضحة',
            'budget' => 500,
        ]);

        $this->assertSame(100, $result['score']);
        $this->assertSame([], $result['missing']);
    }

    public function test_recommender_prioritizes_missing_audience_knowledge(): void
    {
        [$project, $run] = $this->projectAndRun();
        $project->brainFacts()->delete();

        $recommendation = app(MarketingLearningRecommender::class)->next($run->fresh());

        $this->assertSame('describe-real-customer', $recommendation['exercise']['key']);
        $this->assertStringContainsString('عميل', $recommendation['reason']);
    }

    public function test_recommender_skips_a_completed_priority_exercise(): void
    {
        [$project, $run] = $this->projectAndRun();
        $project->brainFacts()->delete();
        $run->attempts()->create([
            'exercise_key' => 'describe-real-customer',
            'answers' => ['customer_profile' => 'صاحب متجر'],
            'status' => MarketingExerciseAttempt::STATUS_COMPLETED,
            'final_score' => 80,
        ]);

        $recommendation = app(MarketingLearningRecommender::class)->next($run->fresh());

        $this->assertNotSame('describe-real-customer', $recommendation['exercise']['key']);
    }

    public function test_prefill_uses_draft_before_completed_work_and_brain(): void
    {
        [$project, $run] = $this->projectAndRun();
        app(BrainWriter::class)->record(
            $project,
            'business.audience',
            'قيمة من ملف المشروع',
            EvidenceLevel::Inferred,
            'test',
        );
        $attempt = $run->attempts()->create([
            'exercise_key' => 'describe-real-customer',
            'answers' => ['customer_profile' => 'قيمة من المسودة'],
            'status' => MarketingExerciseAttempt::STATUS_DRAFT,
        ]);

        $prefill = app(MarketingAnswerPrefill::class)->forQuestion(
            $attempt,
            ['key' => 'customer_profile', 'brain_key' => 'business.audience'],
        );

        $this->assertSame('قيمة من المسودة', $prefill['value']);
        $this->assertSame('draft', $prefill['source']);
    }

    /**
     * @return array{Project, MarketingLearningRun}
     */
    private function projectAndRun(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع خدمات']);

        return [$project, MarketingLearningRun::startFor($project, $user)];
    }
}
