<?php

namespace Tests\Feature;

use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingLearningPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_has_one_reusable_learning_run(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر الاختبار']);

        $first = MarketingLearningRun::startFor($project, $user);
        $second = MarketingLearningRun::startFor($project, $user);

        $this->assertTrue($first->is($second));
        $this->assertSame('active', $first->status);
        $this->assertSame(1, MarketingLearningRun::query()->count());
    }

    public function test_an_attempt_keeps_answers_and_reviews_are_immutable_history(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'شركة التجربة']);
        $run = MarketingLearningRun::startFor($project, $user);

        $attempt = MarketingExerciseAttempt::create([
            'marketing_learning_run_id' => $run->id,
            'exercise_key' => 'describe-real-customer',
            'revision' => 0,
            'answers' => ['customer_profile' => 'صاحب متجر صغير يريد زيادة الطلبات'],
            'status' => 'draft',
        ]);

        $attempt->reviews()->create([
            'revision' => 1,
            'answers' => $attempt->answers,
            'completeness_score' => 100,
            'ai_score' => 80,
            'final_score' => 86,
            'feedback' => ['summary' => 'إجابة جيدة'],
            'catalog_version' => 1,
            'reviewed_at' => now(),
        ]);

        $this->assertSame(86, $attempt->reviews()->first()->final_score);
        $this->assertSame('صاحب متجر صغير يريد زيادة الطلبات', $attempt->answers['customer_profile']);
    }

    public function test_attempts_are_unique_per_run_and_exercise(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع فريد']);
        $run = MarketingLearningRun::startFor($project, $user);

        MarketingExerciseAttempt::create([
            'marketing_learning_run_id' => $run->id,
            'exercise_key' => 'describe-real-customer',
            'answers' => [],
            'status' => 'draft',
        ]);

        $this->expectException(QueryException::class);

        MarketingExerciseAttempt::create([
            'marketing_learning_run_id' => $run->id,
            'exercise_key' => 'describe-real-customer',
            'answers' => [],
            'status' => 'draft',
        ]);
    }
}
