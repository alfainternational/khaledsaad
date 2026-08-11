<?php

namespace Tests\Feature;

use App\Jobs\EvaluateMarketingExercise;
use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\User;
use App\Services\Projects\ProjectService;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExperienceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_experience_selection_activation_and_switch_routes_exist(): void
    {
        foreach ([
            'app.experience.choose',
            'app.experience.select',
            'app.experience.activate',
            'app.experience.enable',
            'app.experience.switch',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
    }

    public function test_learner_is_sent_to_business_activation_before_projects(): void
    {
        $user = User::factory()->create();
        app(ExperienceService::class)->selectInitial($user, Experience::LEARNING);

        $this->actingAs($user)
            ->get('/app/projects')
            ->assertRedirect(route('app.experience.activate', Experience::BUSINESS->value));
    }

    public function test_api_denial_distinguishes_experience_activation_from_plan_upgrade(): void
    {
        $user = User::factory()->create();
        app(ExperienceService::class)->selectInitial($user, Experience::LEARNING);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/projects')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'experience_not_enabled')
            ->assertJsonPath('error.required_experience', 'business');
    }

    public function test_api_can_activate_and_switch_experiences_without_replacing_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = $user->primaryWorkspace();
        app(ExperienceService::class)->selectInitial($user, Experience::LEARNING);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/experiences/business/activate')
            ->assertOk()
            ->assertJsonPath('data.active_experience', 'business')
            ->assertJsonPath('data.enabled_experiences.0', 'business')
            ->assertJsonPath('data.enabled_experiences.1', 'learning');

        $this->actingAs($user->fresh(), 'sanctum')
            ->postJson('/api/v1/experiences/learning/switch')
            ->assertOk()
            ->assertJsonPath('data.active_experience', 'learning');

        $this->assertSame($workspace->id, $user->fresh()->primaryWorkspace()->id);
        $this->assertCount(1, $user->fresh()->workspaces);
    }

    public function test_shared_workspace_members_change_only_their_own_experience(): void
    {
        $owner = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::BUSINESS,
        );
        $member = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $workspace = $owner->primaryWorkspace();

        DB::table('workspace_members')->insert([
            [
                'workspace_id' => $workspace->id,
                'user_id' => $owner->id,
                'role' => 'owner',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'workspace_id' => $workspace->id,
                'user_id' => $member->id,
                'role' => 'member',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/experiences/learning/activate')
            ->assertOk()
            ->assertJsonPath('data.active_experience', 'learning');

        $this->assertSame('learning', $member->fresh()->active_experience?->value);
        $this->assertNull($member->fresh()->business_experience_enabled_at);

        $this->actingAs($member, 'sanctum')
            ->postJson('/api/v1/experiences/business/activate')
            ->assertOk()
            ->assertJsonPath('data.active_experience', 'business');

        $this->assertSame('learning', $owner->fresh()->active_experience?->value);
        $this->assertSame('business', $member->fresh()->active_experience?->value);
        $this->assertSame('business', $owner->fresh()->initial_experience?->value);
        $this->assertSame('learning', $member->fresh()->initial_experience?->value);
        $this->assertSame(2, DB::table('workspace_members')->where('workspace_id', $workspace->id)->count());
    }

    public function test_web_activation_returns_to_intended_url_without_new_workspace(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $workspace = $user->primaryWorkspace();

        $this->actingAs($user)
            ->get('/app/projects')
            ->assertRedirect(route('app.experience.activate', 'business'));

        $this->post(route('app.experience.enable', 'business'))
            ->assertRedirect('/app/projects');

        $this->assertSame($workspace->id, $user->fresh()->primaryWorkspace()->id);
        $this->assertCount(1, $user->fresh()->workspaces);
    }

    public function test_account_experience_page_offers_activation_or_direct_switch_as_needed(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );

        $this->actingAs($user)->get(route('app.experience.choose'))
            ->assertOk()
            ->assertSeeText('فعّل مسار الأعمال');

        $user = app(ExperienceService::class)->activate($user, Experience::BUSINESS);

        $this->actingAs($user)->get(route('app.experience.choose'))
            ->assertOk()
            ->assertSeeText('انتقل إلى مسار الأعمال');
    }

    public function test_learning_api_returns_one_next_application_without_requiring_a_project(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $user->primaryWorkspace();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/learning/marketing')
            ->assertOk()
            ->assertJsonPath('data.project', null)
            ->assertJsonPath('data.next.key', 'marketing-reality-check')
            ->assertJsonCount(1, 'data.primary_actions');
    }

    public function test_learning_api_opens_the_requested_application_and_saves_its_answers(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $user->primaryWorkspace();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/learning/marketing/marketing-reality-check')
            ->assertOk()
            ->assertJsonPath('data.exercise.key', 'marketing-reality-check')
            ->assertJsonPath('data.exercise.questions.0.key', 'current_actions')
            ->assertJsonPath('data.attempt.status', MarketingExerciseAttempt::STATUS_DRAFT)
            ->assertJsonPath('data.attempt.answers', []);

        $answer = 'أنشر محتوى تعليميًا مرتين أسبوعيًا وأراجع عدد طلبات التواصل الناتجة عنه.';

        $this->putJson('/api/v1/learning/marketing/marketing-reality-check/answers/current_actions', [
            'answer' => $answer,
        ])->assertOk()
            ->assertJsonPath('data.attempt.answers.current_actions', $answer)
            ->assertJsonPath('data.next_question_key', 'business_result');

        $this->assertDatabaseHas('marketing_exercise_attempts', [
            'exercise_key' => 'marketing-reality-check',
            'status' => MarketingExerciseAttempt::STATUS_DRAFT,
        ]);
    }

    public function test_learning_api_rechecks_attempt_status_under_lock_before_saving_an_answer(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $run = MarketingLearningRun::startForWorkspace($user->primaryWorkspace(), $user);
        $attempt = $run->attemptFor('marketing-reality-check');
        $attempt->update(['answers' => ['current_actions' => 'الإجابة الأصلية قبل بدء المراجعة.']]);
        $event = 'eloquent.retrieved: '.MarketingExerciseAttempt::class;
        $queuedAfterRead = false;

        Event::listen($event, function (MarketingExerciseAttempt $retrieved) use ($attempt, &$queuedAfterRead): void {
            if ($queuedAfterRead || $retrieved->id !== $attempt->id) {
                return;
            }

            $queuedAfterRead = true;
            DB::table('marketing_exercise_attempts')->where('id', $attempt->id)->update([
                'status' => MarketingExerciseAttempt::STATUS_QUEUED,
            ]);
        });

        try {
            $this->actingAs($user, 'sanctum')
                ->putJson('/api/v1/learning/marketing/marketing-reality-check/answers/current_actions', [
                    'answer' => 'إجابة جديدة يجب ألا تستبدل الإجابة الأصلية أثناء المراجعة.',
                ])
                ->assertConflict()
                ->assertJsonPath('error.code', 'learning_review_in_progress');
        } finally {
            Event::forget($event);
        }

        $attempt->refresh();
        $this->assertTrue($queuedAfterRead);
        $this->assertSame(MarketingExerciseAttempt::STATUS_QUEUED, $attempt->status);
        $this->assertSame('الإجابة الأصلية قبل بدء المراجعة.', $attempt->answers['current_actions']);
    }

    public function test_learning_api_exposes_and_retains_an_optional_owned_project_context(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر المستخدم']);
        $user = app(ExperienceService::class)->activate($user, Experience::BUSINESS);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/learning/marketing/marketing-reality-check')
            ->assertOk()
            ->assertJsonPath('data.project', null)
            ->assertJsonCount(1, 'data.project_choices')
            ->assertJsonPath('data.project_choices.0.id', $project->id)
            ->assertJsonPath('data.project_choices.0.name', 'متجر المستخدم');

        $this->getJson('/api/v1/learning/marketing/marketing-reality-check?project_id='.$project->id)
            ->assertOk()
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.attempt.answers', []);

        $answer = 'أراجع رحلة الشراء في متجر المستخدم وأربط النتائج بهذا المشروع فقط.';
        $this->putJson('/api/v1/learning/marketing/marketing-reality-check/answers/current_actions', [
            'answer' => $answer,
            'project_id' => $project->id,
        ])->assertOk()
            ->assertJsonPath('data.attempt.answers.current_actions', $answer)
            ->assertJsonPath('data.project.id', $project->id);

        $this->getJson('/api/v1/learning/marketing/marketing-reality-check?project_id='.$project->id)
            ->assertOk()
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.attempt.answers.current_actions', $answer);

        $this->getJson('/api/v1/learning/marketing/marketing-reality-check')
            ->assertOk()
            ->assertJsonPath('data.project', null)
            ->assertJsonPath('data.attempt.answers', []);

        $this->assertDatabaseHas('marketing_learning_runs', [
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'started_by' => $user->id,
        ]);
        $this->assertDatabaseHas('marketing_learning_runs', [
            'workspace_id' => $project->workspace_id,
            'project_id' => null,
            'started_by' => $user->id,
        ]);
    }

    public function test_learning_api_never_reveals_or_accepts_projects_outside_the_owned_workspace(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $owned = app(ProjectService::class)->create($user, ['name' => 'المشروع المسموح']);
        $user = app(ExperienceService::class)->activate($user, Experience::BUSINESS);
        $otherUser = User::factory()->create();
        $foreign = app(ProjectService::class)->create($otherUser, ['name' => 'مشروع مستخدم آخر']);
        $otherWorkspace = $user->workspaces()->create(['name' => 'مساحة أخرى', 'slug' => 'other-workspace']);
        $outsidePrimaryWorkspace = $otherWorkspace->projects()->create([
            'name' => 'مشروع مساحة أخرى',
            'slug' => 'outside-primary-workspace',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/learning/marketing')
            ->assertOk()
            ->assertJsonCount(1, 'data.project_choices')
            ->assertJsonPath('data.project_choices.0.id', $owned->id);

        $this->getJson('/api/v1/learning/marketing/marketing-reality-check?project_id='.$foreign->id)
            ->assertNotFound();

        $this->putJson('/api/v1/learning/marketing/marketing-reality-check/answers/current_actions', [
            'answer' => 'هذه الإجابة لا يجوز ربطها بمشروع خارج مساحة العمل الحالية.',
            'project_id' => $outsidePrimaryWorkspace->id,
        ])->assertNotFound();

        $this->assertDatabaseMissing('marketing_learning_runs', ['project_id' => $foreign->id]);
        $this->assertDatabaseMissing('marketing_learning_runs', ['project_id' => $outsidePrimaryWorkspace->id]);
    }

    public function test_learning_api_checks_project_ownership_before_answer_validation(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $user = app(ExperienceService::class)->activate($user, Experience::BUSINESS);
        $foreignProject = app(ProjectService::class)->create(
            User::factory()->create(),
            ['name' => 'مشروع مستخدم آخر'],
        );

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/learning/marketing/marketing-reality-check/answers/current_actions', [
                'answer' => '',
                'project_id' => $foreignProject->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('marketing_learning_runs', ['project_id' => $foreignProject->id]);
    }

    public function test_learning_api_hides_project_context_until_business_is_enabled(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع قبل التفعيل']);
        $user = app(ExperienceService::class)->selectInitial($user, Experience::LEARNING);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/learning/marketing')
            ->assertOk()
            ->assertJsonPath('data.project', null)
            ->assertJsonCount(0, 'data.project_choices');

        $this->getJson('/api/v1/learning/marketing/marketing-reality-check?project_id='.$project->id)
            ->assertNotFound();
    }

    public function test_learning_api_reviews_only_complete_requested_application(): void
    {
        Queue::fake();
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
        $user->primaryWorkspace();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/learning/marketing/marketing-reality-check/review')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'learning_answers_incomplete')
            ->assertJsonPath('error.missing_question', 'current_actions');

        foreach ([
            'current_actions' => 'أنشر محتوى تعليميًا مرتين أسبوعيًا وأتابع الطلبات التي تأتي من كل منشور.',
            'business_result' => 'أريد خمس محادثات بيع مؤهلة أسبوعيًا يمكن ربطها بالمحتوى المنشور.',
        ] as $question => $answer) {
            $this->putJson("/api/v1/learning/marketing/marketing-reality-check/answers/{$question}", [
                'answer' => $answer,
            ])->assertOk();
        }

        $this->postJson('/api/v1/learning/marketing/marketing-reality-check/review')
            ->assertAccepted()
            ->assertJsonPath('data.attempt.status', MarketingExerciseAttempt::STATUS_QUEUED);

        Queue::assertPushed(EvaluateMarketingExercise::class, 1);
    }

    public function test_business_user_can_read_public_learning_content_but_not_open_course_applications(): void
    {
        $user = app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::BUSINESS,
        );

        $this->actingAs($user)->get(route('content.index'))->assertOk();

        $this->get(route('app.learning.marketing.course.exercise', 'marketing-reality-check'))
            ->assertRedirect(route('app.experience.activate', 'learning'));
    }

    public function test_admin_can_reach_both_experience_route_families_without_activation(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get(route('app.projects.index'))->assertOk();
        $this->get(route('app.learning.marketing.home'))->assertOk();
    }
}
