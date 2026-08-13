<?php

namespace Tests\Feature;

use App\Http\Controllers\App\ReportGapController as WebReportGapController;
use App\Models\Project;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Experience\Experience;
use App\Support\Experience\ExperienceService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

/**
 * سدّ الفجوات من التطبيق — نظير مسار الويب واحدًا بواحد.
 *
 * الويب وحده كان محروسًا بـ`ReportGapCompletionTest`، فبقي مسار `api/v1`
 * بلا اختبار: يُقرأ في `routes/api.php` ولا يُثبت أن حدوده هي حدود الويب.
 * وحدّان مختلفان بين سطحين يعنيان أن أحدهما بابٌ خلفيّ — لذلك يؤكّد هذا
 * الملف الحدّ نفسه على السطح الآخر، ويقارن السطحين مباشرةً في الاختبار
 * الأخير بدل الاكتفاء بتكرار التوقّعات.
 */
class ReportGapApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_app_reads_the_open_gaps_with_the_question_that_opens_each(): void
    {
        [$user, $report] = $this->reportWithGap();
        Sanctum::actingAs($user);

        $this->getJson(route('api.v1.reports.gaps.index', $report))
            ->assertOk()
            ->assertJsonPath('data.0.key', 'value_proposition')
            ->assertJsonPath('data.0.label', 'لماذا يشتري منك العميل بدل غيرك؟')
            ->assertJsonPath('data.0.surface', 'profile')
            // النوع يصل صراحةً لأن الشاشة تبني الحقل عليه؛ غيابه يجعل كل
            // فجوة مربع نصّ حتى لو كان سؤالها اختيارًا.
            ->assertJsonPath('data.0.type', 'textarea')
            ->assertJsonPath('run_uuid', $report->toolRun->uuid);
    }

    #[Test]
    public function the_owner_can_answer_from_the_app_and_it_enters_the_project_memory(): void
    {
        [$user, $report] = $this->reportWithGap();
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.reports.gaps.update', $report), [
            'answers' => ['value_proposition' => 'نوصّل الطلب في نفس اليوم داخل الرياض'],
        ])
            ->assertOk()
            ->assertJsonPath('saved.0', 'value_proposition')
            // ما تبقّى يعود مع الحفظ فتُحدَّث الشاشة بلا طلب ثانٍ.
            ->assertJsonPath('remaining', []);

        $this->assertDatabaseHas('project_answers', [
            'project_id' => $report->project_id,
            'field_key' => 'value_proposition',
        ]);

        // الفجوة تُعلَّم مسدودة ولا تُحذف — التقرير صدر بها.
        $gap = collect($report->fresh()->declared_gaps)->firstWhere('key', 'value_proposition');
        $this->assertNotNull($gap['answered_at']);
    }

    #[Test]
    public function a_key_the_report_never_declared_is_rejected(): void
    {
        [$user, $report] = $this->reportWithGap();
        Sanctum::actingAs($user);

        /*
         * الحدّ نفسه الذي يحرس الويب. بدونه يصير مسار التطبيق بابًا يكتب أي
         * حقيقة في الدماغ بمفتاح يختاره من يعدّل الطلب — بلا سؤال يُعرض ولا
         * كفاية تُقاس على نوعه الصحيح.
         */
        $this->putJson(route('api.v1.reports.gaps.update', $report), [
            'answers' => ['monthly_budget' => '9999'],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('project_answers', [
            'project_id' => $report->project_id,
            'field_key' => 'monthly_budget',
        ]);
    }

    #[Test]
    public function an_empty_answer_is_refused_instead_of_saving_nothing_silently(): void
    {
        [$user, $report] = $this->reportWithGap();
        Sanctum::actingAs($user);

        // فراغ يُحفظ بنجاح ظاهر يجعل المستخدم يظن أنه أجاب، وتبقى الفجوة.
        $this->putJson(route('api.v1.reports.gaps.update', $report), [
            'answers' => ['value_proposition' => '   '],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('project_answers', [
            'project_id' => $report->project_id,
            'field_key' => 'value_proposition',
        ]);
    }

    #[Test]
    public function another_users_report_is_not_reachable_from_the_app(): void
    {
        [, $report] = $this->reportWithGap();
        $stranger = app(ExperienceService::class)
            ->selectInitial(User::factory()->create(), Experience::BUSINESS);
        Sanctum::actingAs($stranger);

        // 404 لا 403 عمدًا: 403 يؤكد لمن ليس صاحبه أن التقرير موجود.
        $this->getJson(route('api.v1.reports.gaps.index', $report))->assertNotFound();
        $this->putJson(route('api.v1.reports.gaps.update', $report), [
            'answers' => ['value_proposition' => 'محاولة'],
        ])->assertNotFound();
    }

    #[Test]
    public function the_app_and_the_web_open_exactly_the_same_gaps(): void
    {
        [$user, $report] = $this->reportWithGap();
        Sanctum::actingAs($user);

        $fromApi = collect($this->getJson(route('api.v1.reports.gaps.index', $report))
            ->assertOk()->json('data'))->pluck('key')->all();

        /*
         * السطحان يحسبان «المفتوح» كلٌّ في متحكّمه، فيمكن أن ينحرفا بلا خطأ
         * يظهر: يُصلَح شرط في أحدهما ويبقى الآخر يعرض فجوةً سُدّت أو يُخفي
         * فجوةً قائمة. هذه المقارنة المباشرة هي ما يمنع الانحراف الصامت.
         */
        $open = new ReflectionMethod(WebReportGapController::class, 'open');
        $fromWeb = collect($open->invoke(app(WebReportGapController::class), $report))
            ->pluck('key')->all();

        $this->assertSame($fromWeb, $fromApi);
    }

    /**
     * @return array{0: User, 1: Report}
     */
    private function reportWithGap(): array
    {
        $this->seed(ToolCatalogSeeder::class);

        // مسارات `api/v1` خلف بوابة التجربة كمسارات الويب؛ بلا تفعيلها يردّ
        // الوسيط قبل أن يصل الطلب إلى المتحكّم فتقيس الاختبارات البوابة.
        $user = app(ExperienceService::class)
            ->selectInitial(User::factory()->create(), Experience::BUSINESS);
        $workspace = Workspace::create([
            'owner_id' => $user->id, 'name' => 'مساحة', 'slug' => 'space-'.$user->id,
        ]);
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
            'next_step' => ['title' => 'ابدأ بجملة التموضع', 'description' => 'أعلى أثر في درجتك هذا الأسبوع.'],
            'provenance' => 'automated',
            'schema_version' => 2,
        ]);

        return [$user, $report];
    }
}
