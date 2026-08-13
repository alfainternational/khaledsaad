<?php

namespace Tests\Feature;

use App\Models\Content;
use App\Models\Feature;
use App\Models\MarketingExerciseAttempt;
use App\Models\MarketingLearningRun;
use App\Models\PlanFeature;
use App\Models\User;
use App\Modules\Learning\MarketingLessonAssistant;
use App\Services\Billing\Entitlements;
use App\Services\Projects\ProjectService;
use App\Support\Billing\FeatureKey;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectlessMarketingLearningTest extends TestCase
{
    use RefreshDatabase;

    public function test_entitled_user_can_open_and_start_course_without_a_project(): void
    {
        $user = $this->learningUser();
        $workspace = $user->primaryWorkspace();

        $this->actingAs($user)
            ->get(route('app.learning.marketing.home'))
            ->assertOk()
            ->assertSee('الدرس 1')
            ->assertSee('الدرس 20')
            ->assertDontSee('أضف مشروعك أولًا');

        $this->assertDatabaseHas('marketing_learning_runs', [
            'workspace_id' => $workspace->id,
            'project_id' => null,
            'started_by' => $user->id,
        ]);
    }

    public function test_projectless_user_can_save_an_exercise_answer(): void
    {
        $user = $this->learningUser();
        $user->primaryWorkspace();

        $this->actingAs($user)
            ->put(route('app.learning.marketing.course.save', 'describe-real-customer'), [
                'step' => 1,
                'answer' => 'صاحب متجر إلكتروني صغير يريد فهم سبب مغادرة العملاء قبل الدفع',
            ])
            ->assertRedirect(route('app.learning.marketing.course.exercise', [
                'exercise' => 'describe-real-customer',
                'step' => 2,
            ]));

        $this->assertDatabaseHas('marketing_exercise_attempts', [
            'exercise_key' => 'describe-real-customer',
        ]);
    }

    public function test_learning_entitlement_is_checked_independently_from_projects(): void
    {
        $user = $this->learningUser();
        $workspace = $user->primaryWorkspace();
        $plan = $workspace->subscription()->firstOrFail()->plan;
        $feature = Feature::query()->create([
            'key' => FeatureKey::LEARNING_MARKETING,
            'name' => 'التعلم التطبيقي',
            'description' => 'اختبار',
            'group' => 'core',
            'type' => Feature::TYPE_BOOLEAN,
            'enforcement' => Feature::ENFORCEMENT_GATE,
            'default_enabled' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PlanFeature::query()->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
            'enabled' => false,
        ]);
        app(Entitlements::class)->flush();

        $this->actingAs($user)
            ->get(route('app.learning.marketing.home'))
            ->assertForbidden();
    }

    public function test_legacy_project_learning_url_uses_the_canonical_workspace_course(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)
            ->create($user, ['name' => 'المشروع القديم']);
        $user = app(ExperienceService::class)->selectInitial($user, Experience::BUSINESS);
        $user = app(ExperienceService::class)->activate($user, Experience::LEARNING);

        $this->actingAs($user)
            ->get(route('app.learning.marketing.index', $project))
            ->assertOk()
            ->assertSee(route('app.learning.marketing.course.exercise', [
                'exercise' => 'marketing-reality-check',
                'project' => $project->slug,
            ]), false);
    }

    public function test_public_lesson_links_directly_to_its_exact_applications(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $lesson = Content::query()->where('source_key', 'marketing-course-03')->sole();

        $this->get(route('content.show', $lesson))
            ->assertOk()
            ->assertSee('طبّق هذا الدرس الآن')
            ->assertSee(route('app.learning.marketing.course.exercise', 'describe-real-customer'), false)
            ->assertSee(route('app.learning.marketing.course.exercise', 'customer-clarity-check'), false);
    }

    public function test_lesson_twenty_is_a_public_gallery_for_all_course_applications(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $lesson = Content::query()->where('source_key', 'marketing-course-20')->sole();

        $this->get(route('content.show', $lesson))
            ->assertOk()
            ->assertSee('معرض الدروس والتطبيقات')
            ->assertSee('data-marketing-course-gallery', false)
            ->assertSee('الدرس 1')
            ->assertSee('الدرس 20')
            ->assertSee(route('app.learning.marketing.course.exercise', 'marketing-reality-check'), false)
            ->assertSee('الورشة التطبيقية الكاملة')
            ->assertSee('data-workshop-reference', false)
            ->assertDontSee('مسودة');
    }

    public function test_lesson_twenty_gallery_shows_entitled_users_real_application_progress(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $lesson = Content::query()->where('source_key', 'marketing-course-20')->sole();
        $user = $this->learningUser();
        $run = MarketingLearningRun::startForWorkspace($user->primaryWorkspace(), $user);
        $run->attemptFor('marketing-reality-check')->update([
            'status' => MarketingExerciseAttempt::STATUS_COMPLETED,
            'final_score' => 88,
        ]);
        $run->attemptFor('first-market-reading')->update([
            'status' => MarketingExerciseAttempt::STATUS_DRAFT,
        ]);

        $this->actingAs($user)
            ->get(route('content.show', $lesson))
            ->assertOk()
            ->assertSeeText('1 مكتمل')
            ->assertSeeText('41 متبقٍ')
            ->assertSeeText('88/100')
            ->assertSee(route('app.learning.marketing.course.result', 'marketing-reality-check'), false)
            ->assertSee(route('app.learning.marketing.course.exercise', 'first-market-reading'), false)
            ->assertSee('أكمل');
    }

    public function test_lesson_twenty_gallery_does_not_create_a_run_for_an_unentitled_user(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        $lesson = Content::query()->where('source_key', 'marketing-course-20')->sole();
        $user = $this->learningUser();
        $workspace = $user->primaryWorkspace();
        $plan = $workspace->subscription()->firstOrFail()->plan;
        $feature = Feature::query()->create([
            'key' => FeatureKey::LEARNING_MARKETING,
            'name' => 'التعلم التطبيقي',
            'description' => 'اختبار',
            'group' => 'core',
            'type' => Feature::TYPE_BOOLEAN,
            'enforcement' => Feature::ENFORCEMENT_GATE,
            'default_enabled' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        PlanFeature::query()->create([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
            'enabled' => false,
        ]);
        app(Entitlements::class)->flush();

        $this->actingAs($user)
            ->get(route('content.show', $lesson))
            ->assertOk()
            ->assertSee('هذه التطبيقات غير متاحة في باقتك الحالية')
            ->assertSee(route('app.billing'), false)
            ->assertDontSee('أضف مشروعك');

        $this->assertDatabaseMissing('marketing_learning_runs', [
            'workspace_id' => $workspace->id,
            'started_by' => $user->id,
        ]);
    }

    public function test_existing_project_run_remains_available_after_workspace_linking(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)
            ->create($user, ['name' => 'سجل قائم']);
        $legacy = MarketingLearningRun::startFor($project, $user);
        $legacy->attemptFor('describe-real-customer')->update([
            'answers' => ['customer_profile' => 'إجابة قديمة محفوظة'],
        ]);

        $run = MarketingLearningRun::startForWorkspace($project->workspace, $user, $project);

        $this->assertTrue($legacy->is($run));
        $this->assertSame('إجابة قديمة محفوظة', $run->attemptFor('describe-real-customer')->answers['customer_profile']);
    }

    public function test_question_assistant_receives_full_lesson_context_and_returns_specific_help(): void
    {
        $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
        Content::query()->where('source_key', 'marketing-course-03')->update([
            'body_html' => '<h2>العميل الحقيقي</h2><p>سياق فريد من كامل صفحة الدرس.</p>',
        ]);
        $captured = new \stdClass;
        $this->app->instance(MarketingLessonAssistant::class, new class($captured) extends MarketingLessonAssistant
        {
            public function __construct(private \stdClass $captured) {}

            public function suggest(array $context): array
            {
                $this->captured->context = $context;

                return [
                    'field_help' => 'صف وضع العميل وسلوكه في اللحظة التي يحتاج فيها الحل.',
                    'example' => 'صاحب متجر يدير الطلبات بنفسه ويلاحظ انسحاب العملاء عند الدفع.',
                    'why_it_fits' => 'هذا يطبق مفهوم العميل الحقيقي ومعيار السؤال مباشرة.',
                    'next_action' => 'اكتب موقفًا حقيقيًا واحدًا.',
                    'basis' => ['نص الدرس', 'معيار السؤال'],
                    'evidence_label' => 'فرضية',
                ];
            }
        });
        $user = $this->learningUser();
        $workspace = $user->primaryWorkspace();
        $workspace->forceFill(['monthly_query_limit' => 10])->save();

        $response = $this->actingAs($user)->postJson(
            route('app.learning.marketing.course.assist', [
                'exercise' => 'describe-real-customer',
                'question' => 'customer_profile',
            ]),
        );

        $response->assertOk()
            ->assertJsonPath('data.evidence_label', 'فرضية')
            ->assertJsonPath('data.next_action', 'اكتب موقفًا حقيقيًا واحدًا.');
        $this->assertStringContainsString('سياق فريد من كامل صفحة الدرس', $captured->context['lesson']['full_text']);
        $this->assertSame('من هو العميل الذي تريد خدمته الآن؟', $captured->context['question']['label']);
    }

    public function test_exercise_page_exposes_contextual_ai_help_for_the_exact_question(): void
    {
        $user = $this->learningUser();
        $user->primaryWorkspace();

        $this->actingAs($user)
            ->get(route('app.learning.marketing.course.exercise', 'describe-real-customer'))
            ->assertOk()
            ->assertSee('data-lesson-assist', false)
            ->assertSee('مساعدة مرتبطة بهذا السؤال');
    }

    private function learningUser(): User
    {
        return app(ExperienceService::class)->selectInitial(
            User::factory()->create(),
            Experience::LEARNING,
        );
    }
}
