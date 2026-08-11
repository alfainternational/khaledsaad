<?php

namespace Tests\Feature;

use App\Jobs\EvaluateMarketingExercise;
use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class MarketingLearningJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_recommended_path_and_all_twenty_lessons(): void
    {
        [$user, $project] = $this->project();

        $response = $this->actingAs($user)
            ->get(route('app.learning.marketing.home', ['project' => $project->slug]));

        $response->assertOk()
            ->assertSee('ابدأ من هنا')
            ->assertSee('صف عميلك الحقيقي')
            ->assertSee('الدرس 1')
            ->assertSee('الدرس 20');
    }

    public function test_exercise_page_shows_one_clear_question_and_expected_result(): void
    {
        [$user, $project] = $this->project();

        $response = $this->actingAs($user)->get(route('app.learning.marketing.course.exercise', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
            'step' => 1,
        ]));

        $response->assertOk()
            ->assertSee('من هو العميل الذي تريد خدمته الآن؟')
            ->assertSee('ما الذي ستحصل عليه')
            ->assertSee('وصف واضح للعميل المثالي')
            ->assertDontSee('ما المشكلة التي تدفعه للبحث عن حل مثلك؟');
    }

    public function test_course_index_opens_waiting_and_failed_tasks_in_their_result_state(): void
    {
        [$user, $project] = $this->project();
        $run = MarketingLearningRun::startFor($project, $user);
        $run->attemptFor('describe-real-customer')->update([
            'status' => MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
            'answers' => ['customer_profile' => 'إجابة محفوظة تحتاج إعادة المراجعة بعد تعذر المحاولة السابقة'],
        ]);

        $this->actingAs($user)
            ->get(route('app.learning.marketing.home', ['project' => $project->slug]))
            ->assertOk()
            ->assertSee('أعد المراجعة')
            ->assertSee(route('app.learning.marketing.course.result', ['exercise' => 'describe-real-customer', 'project' => $project->slug]), false);
    }

    public function test_saving_a_step_keeps_the_answer_and_opens_the_next_question(): void
    {
        [$user, $project] = $this->project();

        $response = $this->actingAs($user)->put(route('app.learning.marketing.course.save', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
        ]), [
            'step' => 1,
            'answer' => 'صاحب متجر إلكتروني صغير يدير الطلبات بنفسه ويريد زيادة المبيعات',
        ]);

        $response->assertRedirect(route('app.learning.marketing.course.exercise', [
            'exercise' => 'describe-real-customer',
            'step' => 2,
        ]));

        $this->assertDatabaseHas('marketing_exercise_attempts', [
            'exercise_key' => 'describe-real-customer',
            'status' => MarketingExerciseAttempt::STATUS_DRAFT,
        ]);
        $this->assertSame(
            'صاحب متجر إلكتروني صغير يدير الطلبات بنفسه ويريد زيادة المبيعات',
            MarketingExerciseAttempt::query()->firstOrFail()->answers['customer_profile'],
        );
    }

    public function test_complete_answers_are_queued_once_for_review(): void
    {
        Queue::fake();
        [$user, $project] = $this->project();
        $run = MarketingLearningRun::startFor($project, $user);
        $attempt = $run->attemptFor('describe-real-customer');
        $attempt->update(['answers' => [
            'customer_profile' => 'صاحب متجر إلكتروني صغير يدير الطلبات بنفسه ويريد زيادة المبيعات',
            'customer_problem' => 'لا يعرف لماذا يزور الناس متجره ثم يغادرون من غير إكمال الطلب',
            'buying_trigger' => 'يتحرك عندما تنخفض الطلبات أسبوعين ويحتاج إلى استعادة الدخل سريعًا',
        ]]);

        $response = $this->actingAs($user)->post(route('app.learning.marketing.course.submit', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
        ]));

        $response->assertRedirect(route('app.learning.marketing.course.result', [
            'exercise' => 'describe-real-customer',
        ]));
        $this->assertSame(MarketingExerciseAttempt::STATUS_QUEUED, $attempt->refresh()->status);
        Queue::assertPushed(EvaluateMarketingExercise::class, 1);

        $this->actingAs($user)->post(route('app.learning.marketing.course.submit', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
        ]));
        Queue::assertPushed(EvaluateMarketingExercise::class, 1);
    }

    public function test_incomplete_attempt_returns_to_the_missing_question_without_queueing(): void
    {
        Queue::fake();
        [$user, $project] = $this->project();
        $run = MarketingLearningRun::startFor($project, $user);
        $run->attemptFor('describe-real-customer')->update(['answers' => [
            'customer_profile' => 'صاحب متجر إلكتروني صغير يدير الطلبات بنفسه ويريد زيادة المبيعات',
        ]]);

        $response = $this->actingAs($user)->post(route('app.learning.marketing.course.submit', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
        ]));

        $response->assertRedirect(route('app.learning.marketing.course.exercise', [
            'exercise' => 'describe-real-customer',
            'step' => 2,
        ]));
        $response->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_a_completed_attempt_is_not_reviewed_again_until_an_answer_changes(): void
    {
        Queue::fake();
        [$user, $project] = $this->project();
        $run = MarketingLearningRun::startFor($project, $user);
        $attempt = $run->attemptFor('describe-real-customer');
        $attempt->update([
            'answers' => [
                'customer_profile' => 'صاحب متجر إلكتروني صغير يدير الطلبات بنفسه ويريد زيادة المبيعات',
                'customer_problem' => 'لا يعرف لماذا يزور الناس متجره ثم يغادرون من غير إكمال الطلب',
                'buying_trigger' => 'يتحرك عندما تنخفض الطلبات أسبوعين ويحتاج إلى استعادة الدخل سريعًا',
            ],
            'status' => MarketingExerciseAttempt::STATUS_COMPLETED,
            'final_score' => 82,
        ]);

        $this->actingAs($user)->post(route('app.learning.marketing.course.submit', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
        ]))->assertRedirect(route('app.learning.marketing.course.result', [
            'exercise' => 'describe-real-customer',
        ]));

        Queue::assertNothingPushed();
        $this->assertSame(MarketingExerciseAttempt::STATUS_COMPLETED, $attempt->refresh()->status);
    }

    public function test_answers_cannot_change_while_the_review_is_running(): void
    {
        [$user, $project] = $this->project();
        $run = MarketingLearningRun::startFor($project, $user);
        $attempt = $run->attemptFor('describe-real-customer');
        $original = 'صاحب متجر إلكتروني صغير يدير الطلبات بنفسه ويريد زيادة المبيعات';
        $attempt->update([
            'answers' => ['customer_profile' => $original],
            'status' => MarketingExerciseAttempt::STATUS_EVALUATING,
        ]);

        $this->actingAs($user)->put(route('app.learning.marketing.course.save', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
        ]), [
            'step' => 1,
            'answer' => 'إجابة جديدة يجب ألا تختلط بالمراجعة التي تعمل الآن',
        ])->assertRedirect(route('app.learning.marketing.course.result', [
            'exercise' => 'describe-real-customer',
        ]));

        $this->assertSame($original, $attempt->refresh()->answers['customer_profile']);
        $this->assertSame(MarketingExerciseAttempt::STATUS_EVALUATING, $attempt->status);
    }

    public function test_queue_failure_marks_the_attempt_for_plain_language_retry(): void
    {
        [$user, $project] = $this->project();
        $run = MarketingLearningRun::startFor($project, $user);
        $attempt = $run->attemptFor('describe-real-customer');
        $attempt->update(['answers' => [
            'customer_profile' => 'صاحب متجر إلكتروني صغير يدير الطلبات بنفسه ويريد زيادة المبيعات',
            'customer_problem' => 'لا يعرف لماذا يزور الناس متجره ثم يغادرون من غير إكمال الطلب',
            'buying_trigger' => 'يتحرك عندما تنخفض الطلبات أسبوعين ويحتاج إلى استعادة الدخل سريعًا',
        ]]);
        $this->mock(BusDispatcher::class)
            ->shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('queue unavailable'));

        $this->actingAs($user)->post(route('app.learning.marketing.course.submit', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
        ]))->assertRedirect(route('app.learning.marketing.course.result', [
            'exercise' => 'describe-real-customer',
        ]));

        $attempt->refresh();
        $this->assertSame(MarketingExerciseAttempt::STATUS_REVIEW_FAILED, $attempt->status);
        $this->assertNotEmpty($attempt->answers);
        $this->assertNull($attempt->final_score);
    }

    public function test_retry_queues_a_failed_review_only_once(): void
    {
        Queue::fake();
        [$user, $project] = $this->project();
        $run = MarketingLearningRun::startFor($project, $user);
        $attempt = $run->attemptFor('describe-real-customer');
        $attempt->update([
            'answers' => ['customer_profile' => 'إجابة محفوظة للمراجعة مرة أخرى بعد تعذر المحاولة السابقة'],
            'status' => MarketingExerciseAttempt::STATUS_REVIEW_FAILED,
        ]);

        $route = route('app.learning.marketing.course.retry', ['exercise' => 'describe-real-customer', 'project' => $project->slug]);
        $this->actingAs($user)->post($route)->assertRedirect();
        $this->actingAs($user)->post($route)->assertRedirect();

        Queue::assertPushed(EvaluateMarketingExercise::class, 1);
        $this->assertSame(MarketingExerciseAttempt::STATUS_QUEUED, $attempt->refresh()->status);
    }

    public function test_result_explains_each_answer_and_the_next_action_in_plain_language(): void
    {
        [$user, $project] = $this->project();
        $run = MarketingLearningRun::startFor($project, $user);
        $attempt = $run->attemptFor('describe-real-customer');
        $attempt->update([
            'answers' => [
                'customer_profile' => 'صاحب متجر إلكتروني صغير يدير الطلبات بنفسه ويريد زيادة المبيعات',
                'customer_problem' => 'لا يعرف لماذا يزور الناس متجره ثم يغادرون من غير إكمال الطلب',
                'buying_trigger' => 'يتحرك عندما تنخفض الطلبات أسبوعين ويحتاج إلى استعادة الدخل سريعًا',
            ],
            'status' => MarketingExerciseAttempt::STATUS_COMPLETED,
            'completeness_score' => 100,
            'ai_score' => 80,
            'final_score' => 86,
            'feedback' => [
                'input_feedback' => [
                    ['key' => 'customer_profile', 'score' => 84, 'comment' => 'حددت نوع العميل ووضعه بوضوح.', 'suggestion' => 'أضف حجم المتجر أو عدد طلباته الحالي.'],
                    ['key' => 'customer_problem', 'score' => 80, 'comment' => 'المشكلة مرتبطة بسلوك يحدث فعلًا.', 'suggestion' => 'اذكر أين ينسحب العميل من الطلب.'],
                    ['key' => 'buying_trigger', 'score' => 76, 'comment' => 'وصفت اللحظة التي تدفعه للتحرك.', 'suggestion' => 'حدد الانخفاض الذي يجعله يبدأ البحث.'],
                ],
                'overall_score' => 80,
                'strengths' => ['وصفك قريب من واقع صاحب المتجر.'],
                'improvements' => ['تحتاج إلى رقم يوضح حجم المشكلة.'],
                'next_action' => 'تحدث اليوم مع صاحب متجر واحد واسأله أين تتوقف طلباته.',
                'deliverable' => 'عميلنا صاحب متجر إلكتروني صغير يدير طلباته بنفسه ويبحث عن زيادة المبيعات.',
            ],
        ]);
        foreach ([72, 86] as $revision => $score) {
            $attempt->reviews()->create([
                'revision' => $revision + 1,
                'answers' => $attempt->answers,
                'completeness_score' => 100,
                'ai_score' => $score,
                'final_score' => $score,
                'feedback' => $attempt->feedback,
                'catalog_version' => 1,
                'reviewed_at' => now()->subDays(1 - $revision),
            ]);
        }

        $response = $this->actingAs($user)->get(route('app.learning.marketing.course.result', [
            'exercise' => 'describe-real-customer',
            'project' => $project->slug,
        ]));

        $response->assertOk()
            ->assertSee('86')
            ->assertSee('تقييم إجاباتك')
            ->assertSee('حددت نوع العميل ووضعه بوضوح.')
            ->assertSee('خطوتك التالية')
            ->assertSee('تحدث اليوم مع صاحب متجر واحد')
            ->assertSee('المخرج الجاهز لك')
            ->assertSee('نتائجك السابقة في هذه المهمة')
            ->assertSee('72/100');
    }

    public function test_another_user_cannot_see_the_course_or_answers_for_the_project(): void
    {
        [$owner, $project] = $this->project();
        $stranger = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );

        $this->actingAs($stranger)
            ->get(route('app.learning.marketing.home', ['project' => $project->slug]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('app.learning.marketing.home', ['project' => $project->slug]))
            ->assertOk();
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function project(): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر الاختبار']);
        $project->brainFacts()->delete();
        $user = app(ExperienceService::class)->selectInitial($user, Experience::BUSINESS);
        $user = app(ExperienceService::class)->activate($user, Experience::LEARNING);

        return [$user, $project];
    }
}
