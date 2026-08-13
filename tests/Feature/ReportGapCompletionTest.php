<?php

namespace Tests\Feature;

use App\Models\Objective;
use App\Models\Project;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Reporting\Templates\TemplateResolver;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use Database\Seeders\ReportingContractSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الفجوة المعلنة يجب أن تكون قابلة للسدّ، لا جملةً تُقرأ.
 */
class ReportGapCompletionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_declared_gap_carries_a_key_that_opens_its_question(): void
    {
        [$user, $report] = $this->reportWithGap();

        $this->actingAs($user)
            ->get(route('app.reports.show', $report))
            ->assertOk()
            ->assertSee(route('app.reports.gaps.edit', $report), false);
    }

    #[Test]
    public function the_owner_can_answer_a_declared_gap_and_it_enters_the_project_memory(): void
    {
        [$user, $report] = $this->reportWithGap();

        $this->actingAs($user)
            ->put(route('app.reports.gaps.update', $report), [
                'answers' => ['value_proposition' => 'نوصّل الطلب في نفس اليوم داخل الرياض'],
            ])
            ->assertRedirect(route('app.reports.show', $report));

        $this->assertDatabaseHas('project_answers', [
            'project_id' => $report->project_id,
            'field_key' => 'value_proposition',
        ]);

        // الفجوة تُعلَّم مسدودة ولا تُحذف: التقرير صدر بها، وإخفاؤها بأثر
        // رجعيّ يجعل نسخته المطبوعة تخالف نسخته على الشاشة.
        $gap = collect($report->fresh()->declared_gaps)->firstWhere('key', 'value_proposition');
        $this->assertNotNull($gap['answered_at']);
    }

    #[Test]
    public function a_key_the_report_never_declared_is_rejected(): void
    {
        [$user, $report] = $this->reportWithGap();

        $this->actingAs($user)->put(route('app.reports.gaps.update', $report), [
            'answers' => ['monthly_budget' => '9999'],
        ]);

        // بلا هذا الحدّ يصير النموذج بابًا يكتب أي حقيقة في الدماغ بمفتاح
        // يختاره من يعدّل الطلب، بلا سؤال يُعرض ولا كفاية تُقاس.
        $this->assertDatabaseMissing('project_answers', [
            'project_id' => $report->project_id,
            'field_key' => 'monthly_budget',
        ]);
    }

    #[Test]
    public function another_users_report_is_not_reachable(): void
    {
        [, $report] = $this->reportWithGap();
        $stranger = app(ExperienceService::class)->selectInitial(User::factory()->create(), Experience::BUSINESS);

        // المشروع يرفض بـ404 لا 403 عمدًا (`ResolvesWorkspace::assert`): 403
        // يؤكد لمن ليس صاحبه أن التقرير موجود.
        $this->actingAs($stranger)
            ->get(route('app.reports.gaps.edit', $report))
            ->assertNotFound();
    }

    #[Test]
    public function a_report_without_open_gaps_sends_the_owner_back(): void
    {
        [$user, $report] = $this->reportWithGap();
        $report->forceFill(['declared_gaps' => []])->save();

        $this->actingAs($user)
            ->get(route('app.reports.gaps.edit', $report))
            ->assertRedirect(route('app.reports.show', $report));
    }

    /**
     * @return array{0: User, 1: Report}
     */
    private function reportWithGap(): array
    {
        $this->seed(ToolCatalogSeeder::class);

        // مسارات التطبيق خلف بوابة التجربة؛ بلا تفعيلها يعيد الوسيط تحويلًا
        // قبل أن يصل الطلب إلى المتحكّم فتقيس الاختبارات البوابة لا السلوك.
        $user = app(ExperienceService::class)->selectInitial(User::factory()->create(), Experience::BUSINESS);
        $workspace = Workspace::create(['owner_id' => $user->id, 'name' => 'مساحة', 'slug' => 'space-'.$user->id]);
        $project = Project::create([
            'workspace_id' => $workspace->id, 'name' => 'متجر أفق', 'slug' => 'afaq-'.$user->id,
            'sector' => 'ecommerce', 'stage' => 'growth', 'status' => 'active',
        ]);

        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = ToolRun::create([
            'uuid' => (string) str()->uuid(),
            'tool_version_id' => $tool->current_version_id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'locale' => 'ar',
        ]);

        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير',
            'locale' => 'ar',
            'status' => 'draft',
            'score' => 50,
            'score_band' => 'متوسط',
            'summary' => 'ملخص',
            'assumptions' => [],
            'declared_gaps' => [[
                'key' => 'value_proposition',
                'label' => 'لماذا يشتري منك العميل بدل غيرك؟',
                'help' => null,
                'source' => 'profile',
                'why' => 'بدونه لا يمكن الحكم على تمايزك.',
                'origin' => 'unanswered',
            ]],
            // `ReportPresenter` يمرّر `next_step` كما هو إلى `executive_summary.this_week`،
            // والقالب يقرأ منه `title`.
            'next_step' => ['title' => 'ابدأ بجملة التموضع', 'description' => 'أعلى أثر في درجتك هذا الأسبوع.'],
            'provenance' => 'automated',
            'schema_version' => 2,
        ]);

        return [$user, $report];
    }

    #[Test]
    public function a_non_arabic_run_still_gets_a_worksheet_instead_of_a_dead_end(): void
    {
        $this->seed(ReportingContractSeeder::class);

        /*
         * قبل الإصلاح: `forObjective` توقيعها `$locale = 'ar'` والمستدعي لا
         * يمرّر شيئًا، وكل القوالب مبذورة بالعربية — فكل تشغيل غير عربي كان
         * يصل موسومًا «هذه التوصية غير جاهزة للتنفيذ» بلا خطأ في السجل.
         */
        $resolved = app(TemplateResolver::class)->forObjective(
            'clarify-offer',
            ['project' => ['name' => 'Ofok Store']],
            'en',
        );

        $this->assertNotNull($resolved);
        $this->assertSame('en', $resolved->locale);
        $this->assertSame('Ofok Store', $resolved->blocks[0]['value']);
    }

    #[Test]
    public function an_unanswered_binding_becomes_a_declared_gap_not_a_hidden_sheet(): void
    {
        $this->seed(ToolCatalogSeeder::class);
        $this->seed(ReportingContractSeeder::class);

        $resolved = app(TemplateResolver::class)->forObjective(
            'clarify-offer',
            ['project' => ['name' => 'متجر أفق'], 'answers' => []],
        );

        $this->assertNotNull($resolved, 'الورقة تخرج ناقصةً معلنة النقص، ولا تُحجب.');
        $this->assertNotSame([], $resolved->gaps);
        $this->assertContains('what_included', collect($resolved->gaps)->pluck('key')->all());

        // النائب بلا جواب يُستبدل بعلامة مقروءة لا يُترك `{what_included}` خامًا
        // في ورقة تُطبع وتُسلَّم.
        $values = collect($resolved->blocks)->pluck('value')->implode(' ');
        $this->assertStringNotContainsString('{what_included}', $values);
    }

    #[Test]
    public function objectives_no_longer_share_one_identical_body(): void
    {
        $this->seed(ReportingContractSeeder::class);

        $bodies = collect(['clarify-offer', 'define-audience', 'select-growth-channels'])
            ->map(fn (string $slug) => app(TemplateResolver::class)->forObjective(
                $slug,
                ['project' => ['name' => 'متجر أفق']],
            ))
            ->map(fn ($template) => collect($template->blocks)->pluck('label')->implode('|'));

        $this->assertCount(3, $bodies->unique(), 'كل هدف يعد بأصل مختلف، فلا يجوز أن يتشارك ثلاثتها جسدًا واحدًا.');
    }

    #[Test]
    public function every_seeded_objective_has_a_resolvable_template(): void
    {
        $this->seed(ReportingContractSeeder::class);

        foreach (Objective::pluck('slug') as $slug) {
            $this->assertNotNull(
                app(TemplateResolver::class)->forObjective($slug, ['project' => ['name' => 'متجر أفق']]),
                "الهدف {$slug} بلا قالب قابل للبناء.",
            );
        }
    }
}
