<?php

namespace Tests\Feature;

use App\Models\ContentPlan;
use App\Models\ContentPost;
use App\Models\Project;
use App\Models\User;
use App\Services\Content\ContentPlanDocxImporter;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContentPlanDashboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_plan_belongs_to_an_owned_project_and_derives_progress_from_steps(): void
    {
        [$user, $project] = $this->project();
        $plan = $this->plan($user, $project);
        $post = $plan->posts->first();

        $post->update(['designed_at' => now(), 'reviewed_at' => now()]);

        $this->assertTrue($project->contentPlans()->whereKey($plan)->exists());
        $this->assertSame(50, $post->fresh()->progressPercent());
        $this->assertSame(ContentPost::STAGE_REVIEWED, $post->fresh()->workflowStage());
    }

    #[Test]
    public function it_imports_cards_specifications_and_safety_rules_atomically(): void
    {
        [$user, $project] = $this->project();

        $plan = app(ContentPlanDocxImporter::class)->import(
            $this->contentPlanFixture(),
            $project,
            $user,
        );

        $this->assertSame('خطة المحتوى الرقمي لشهر أغسطس 2026م', $plan->title);
        $this->assertSame('2026-08-01', $plan->month->toDateString());
        $this->assertCount(2, $plan->posts);
        $this->assertSame('الموقع والأثر', $plan->posts->first()->pillar);
        $this->assertStringContainsString('التوطين يبدأ بالتأهيل', $plan->posts->first()->x_content);
        $this->assertStringContainsString('بطاقة نصية', $plan->posts->first()->design_brief);
        $this->assertSame(['#الحدود_الشمالية'], $plan->posts->first()->hashtags);
        $this->assertContains('لا نصيحة علاجية', $plan->safety_rules);
        $this->assertArrayHasKey('مقاسات X (تويتر)', $plan->design_specifications);
    }

    #[Test]
    public function invalid_documents_leave_no_partial_plan(): void
    {
        [$user, $project] = $this->project();
        $word = new PhpWord;
        $word->addSection()->addText('ملف لا يحتوي على بطاقات منشورات');
        $path = tempnam(sys_get_temp_dir(), 'invalid-content-plan-').'.docx';
        IOFactory::createWriter($word)->save($path);

        try {
            app(ContentPlanDocxImporter::class)->import($path, $project, $user);
            $this->fail('The invalid document was accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('content_plans', 0);
            $this->assertDatabaseCount('content_posts', 0);
        }
    }

    #[Test]
    public function a_user_can_import_into_their_project_but_cannot_open_a_foreign_plan(): void
    {
        [$user, $project] = $this->project();
        [$other, $otherProject] = $this->project('مشروع آخر');
        $foreign = $this->plan($other, $otherProject);
        $path = $this->contentPlanFixture();

        $this->actingAs($user)
            ->post(route('app.content-plans.import'), [
                'project_id' => $project->id,
                'document' => new UploadedFile(
                    $path,
                    'خطة-أغسطس.docx',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    null,
                    true,
                ),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('content_plans', [
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        $this->actingAs($user)
            ->get(route('app.content-plans.show', $foreign))
            ->assertNotFound();
    }

    #[Test]
    public function workflow_and_metrics_are_managed_without_skipping_review(): void
    {
        [$user, $project] = $this->project();
        $post = $this->plan($user, $project)->posts->first();

        $this->actingAs($user)
            ->patch(route('app.content-posts.workflow', $post), ['step' => 'reviewed', 'completed' => 1])
            ->assertSessionHasErrors('workflow');

        $this->actingAs($user)
            ->patch(route('app.content-posts.workflow', $post), ['step' => 'designed', 'completed' => 1])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('app.content-posts.workflow', $post), ['step' => 'reviewed', 'completed' => 1])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('app.content-posts.metrics', $post), ['x_reach' => 100, 'x_engagement' => 12])
            ->assertSessionHasErrors('metrics');

        $this->actingAs($user)
            ->patch(route('app.content-posts.workflow', $post), ['step' => 'x_published', 'completed' => 1])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('app.content-posts.metrics', $post), ['x_reach' => 100, 'x_engagement' => 12])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('content_posts', [
            'id' => $post->id,
            'x_reach' => 100,
            'x_engagement' => 12,
        ]);
    }

    #[Test]
    public function text_only_posts_skip_design_without_losing_progress(): void
    {
        [$user, $project] = $this->project();
        $post = $this->plan($user, $project)->posts->first();
        $post->update(['requires_design' => false, 'designed_at' => null]);

        $this->actingAs($user)
            ->patch(route('app.content-posts.workflow', $post), ['step' => 'reviewed', 'completed' => 1])
            ->assertSessionHasNoErrors();

        $post->refresh();
        $this->assertSame(ContentPost::STAGE_REVIEWED, $post->workflowStage());
        $this->assertSame(33, $post->progressPercent());

        $this->actingAs($user)
            ->get(route('app.content-plans.show', $post->plan))
            ->assertOk()
            ->assertSee('لا يحتاج تصميمًا', false);
    }

    #[Test]
    public function the_dashboard_exposes_all_operational_views_and_source_rules(): void
    {
        [$user, $project] = $this->project();
        $plan = $this->plan($user, $project);

        $this->actingAs($user)
            ->get(route('app.content-plans.show', $plan))
            ->assertOk()
            ->assertSee('تقويم النشر', false)
            ->assertSee('مسار التنفيذ', false)
            ->assertSee('الجدول التشغيلي', false)
            ->assertSee('قواعد الأمان التحريري', false)
            ->assertSee('data-copy-content', false);
    }

    #[Test]
    public function posts_can_be_edited_added_and_archived(): void
    {
        [$user, $project] = $this->project();
        $plan = $this->plan($user, $project);
        $post = $plan->posts->first();

        $this->actingAs($user)
            ->patch(route('app.content-posts.update', $post), [
                'title' => 'عنوان محدث',
                'publish_at' => '2026-08-04 09:00',
                'pillar' => 'المعرفة المهنية',
                'x_content' => 'نص منصة X بعد التحديث ويحتوي تفاصيل كافية.',
                'linkedin_content' => 'نص لينكد إن بعد التحديث ويحتوي تفاصيل كافية.',
                'design_brief' => 'موجز تصميم محدث.',
                'publishing_notes' => 'ملاحظات نشر محدثة.',
                'alt_text' => 'وصف بديل محدث.',
                'hashtags_text' => '#التمريض #التعليم',
            ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('app.content-posts.store', $plan), [
                'title' => 'منشور إضافي',
                'publish_at' => '2026-08-31 12:00',
                'pillar' => 'تفاعل',
                'x_content' => 'نص إضافي جديد لمنصة X يحتوي تفاصيل كافية.',
                'linkedin_content' => 'نص إضافي جديد للينكد إن يحتوي تفاصيل كافية.',
            ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('app.content-posts.archive', $post))
            ->assertSessionHasNoErrors();

        $this->assertSame('عنوان محدث', $post->fresh()->title);
        $this->assertNotNull($post->fresh()->archived_at);
        $this->assertCount(2, $plan->posts()->get());
    }

    #[Test]
    public function dashboard_assets_include_views_filters_and_copy_feedback(): void
    {
        $js = file_get_contents(resource_path('js/app.js'));
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('[data-content-view]', $js);
        $this->assertStringContainsString('[data-content-search]', $js);
        $this->assertStringContainsString('[data-copy-content]', $js);
        $this->assertStringContainsString('.content-dashboard', $css);
        $this->assertStringContainsString('@media (max-width: 760px)', $css);
    }

    /** @return array{0: User, 1: Project} */
    private function project(string $name = 'شركة الشمال التعليمية'): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => $name,
            'industry' => 'التعليم الصحي',
            'stage' => 'growth',
        ]);

        return [$user, $project];
    }

    private function plan(User $user, Project $project): ContentPlan
    {
        $plan = ContentPlan::create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'خطة المحتوى الرقمي لشهر أغسطس 2026م',
            'month' => '2026-08-01',
            'status' => ContentPlan::STATUS_ACTIVE,
            'design_specifications' => ['مقاسات X (تويتر)' => '1080×1080'],
            'publishing_specifications' => ['حد الأحرف في X' => '280'],
            'activity_protocol' => [['الحالة' => 'وصل الخبر في يومه', 'الشكل' => 'منشور مباشر']],
            'safety_rules' => ['لا نصيحة علاجية'],
        ]);

        $plan->posts()->create([
            'position' => 1,
            'publish_at' => '2026-08-03 09:00:00',
            'pillar' => 'الموقع والأثر',
            'title' => 'التوطين يبدأ بالتأهيل',
            'x_content' => 'توطين الكوادر الصحية لا يبدأ بالتوظيف، بل بالتأهيل.',
            'linkedin_content' => 'التحدي في القطاع الصحي ليس تحدي مبانٍ بقدر ما هو تحدي كوادر مؤهلة.',
            'design_brief' => 'بطاقة نصية بخلفية داكنة.',
            'publishing_notes' => 'X: ٩ صباحًا، لينكد إن: ٩:٣٠ صباحًا.',
            'alt_text' => 'بطاقة نصية تحمل عبارة التوطين يبدأ بالتأهيل.',
            'hashtags' => ['#الحدود_الشمالية'],
        ]);

        return $plan->load('posts');
    }

    private function contentPlanFixture(): string
    {
        $word = new PhpWord;
        $section = $word->addSection();
        $section->addText('إدارة العلاقات العامة والتسويق');
        $section->addText('خطة المحتوى الرقمي لشهر أغسطس 2026م');

        $design = $section->addTable();
        $row = $design->addRow();
        $row->addCell()->addText('مقاسات X (تويتر)');
        $row->addCell()->addText('مربع ١٠٨٠×١٠٨٠ بكسل');
        $row = $design->addRow();
        $row->addCell()->addText('صيغ التسليم');
        $row->addCell()->addText('PNG + JPG');

        $publishing = $section->addTable();
        $row = $publishing->addRow();
        $row->addCell()->addText('حد الأحرف في X');
        $row->addCell()->addText('٢٨٠ حرفاً');

        $this->addFixturePost($section, '٠١', '٣', 'الموقع والأثر', 'التوطين يبدأ بالتأهيل', '#الحدود_الشمالية');
        $this->addFixturePost($section, '٠٢', '٥', 'المعرفة المهنية', 'الشهادة واحدة والأبواب كثيرة', '#التمريض');

        $activity = $section->addTable();
        $row = $activity->addRow();
        $row->addCell()->addText('الحالة');
        $row->addCell()->addText('الشكل المناسب للنشر');
        $row = $activity->addRow();
        $row->addCell()->addText('وصلك الخبر في يومه');
        $row->addCell()->addText('منشور مباشر');

        $safety = $section->addTable();
        $row = $safety->addRow();
        $row->addCell()->addText('1');
        $row->addCell()->addText('لا نصيحة علاجية');

        $path = tempnam(sys_get_temp_dir(), 'content-plan-').'.docx';
        IOFactory::createWriter($word)->save($path);

        return $path;
    }

    private function addFixturePost($section, string $number, string $day, string $pillar, string $headline, string $hashtag): void
    {
        $table = $section->addTable();
        $table->addRow()->addCell()->addText("منشور {$number} · الاثنين {$day} أغسطس ٢٠٢٦ · {$pillar}");
        $row = $table->addRow();
        $row->addCell()->addText('نص منشور X (تويتر)');
        $row->addCell()->addText("{$headline}. {$hashtag}");
        $row = $table->addRow();
        $row->addCell()->addText('نص منشور لينكد إن');
        $row->addCell()->addText("نسخة لينكد إن: {$headline} ضمن سياق مهني كامل.");
        $row = $table->addRow();
        $row->addCell()->addText('موجز التصميم للمصمم');
        $row->addCell()->addText('بطاقة نصية');
        $row = $table->addRow();
        $row->addCell()->addText('ملاحظات النشر للناشر');
        $row->addCell()->addText("الهاشتاقات: {$hashtag}\nالنص البديل (Alt): بطاقة نصية عن {$headline}");
    }
}
