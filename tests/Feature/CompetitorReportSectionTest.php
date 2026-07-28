<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\ToolRunAnswer;
use App\Models\User;
use App\Modules\Diagnosis\DeterministicScorer;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ReportComposer;
use App\Services\Tools\ToolRunService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الاستفادة في التقرير: المنصات التي يختارها العميل تتحول إلى قسم مراقبة
 * منافسين — بروابط مكتبات إعلاناتهم وما يبحث عنه — لا تبقى إدخالًا صامتًا.
 */
class CompetitorReportSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // الفوترة صارت شرطًا في إنشاء المشروع (خطة مجانية افتراضية).
        $this->seed([PlanSeeder::class, ToolCatalogSeeder::class]);
    }

    #[Test]
    public function the_report_carries_a_competitor_watch_section_from_the_chosen_platforms(): void
    {
        $section = $this->composeCampaignReport(['meta', 'google_search', 'youtube', 'tiktok']);

        $this->assertNotNull($section, 'يجب أن يظهر قسم مراقبة المنافسين حين تُختار منصات.');

        $watchlist = $section->content_json['watchlist'];
        $sources = collect($watchlist)->pluck('source');

        // منصات Meta وGoogle تُدمج، فلا تكرار للمصدر الواحد.
        $this->assertSame($sources->count(), $sources->unique()->count());
        $this->assertTrue($sources->contains('مكتبة إعلانات Meta'));
        $this->assertTrue($sources->contains('مركز شفافية إعلانات Google'));

        // إرشاد عملي: ماذا يبحث عنه في كل مكتبة.
        $this->assertNotEmpty($section->content_json['look_for']);
    }

    #[Test]
    public function no_competitor_section_when_no_platforms_and_no_named_competitors(): void
    {
        $section = $this->composeCampaignReport([]);

        $this->assertNull($section, 'بلا منصات مختارة ولا منافسين مسمّين لا يُفرض قسم فارغ.');
    }

    #[Test]
    public function naming_competitors_captures_them_and_makes_watching_a_task(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر عسل']);
        $tool = Tool::where('key', 'competitor-lens')->firstOrFail();

        $run = app(ToolRunService::class)->start($project, $tool, $user);
        ToolRunAnswer::updateOrCreate(
            ['tool_run_id' => $run->id, 'field_key' => 'competitor_names'],
            ['value_json' => ['value' => 'عسل الحاج، @honey_sd، مناحل النيل'], 'source' => ToolRunAnswer::SOURCE_USER],
        );

        $run = $run->fresh()->load('toolVersion.fields', 'answers');
        $answers = collect($run->answerMap())
            ->map(fn ($v) => is_array($v) && array_key_exists('value', $v) ? $v['value'] : $v)
            ->all();
        $baseline = app(DeterministicScorer::class)->score($run->toolVersion, $answers);
        $report = app(ReportComposer::class)->compose($run, $baseline, [], null, null);

        // المنافسون المحليون خُزّنوا على مستوى المشروع.
        $this->assertSame(3, $project->competitors()->count());

        // القسم يعرضهم مؤكدين، بلا دعوة لتحديد محليين (لأنه سمّاهم).
        $section = $report->sections()->where('key', 'competitors')->firstOrFail();
        $this->assertCount(3, $section->content_json['confirmed']);
        $this->assertFalse($section->content_json['prompt_local']);

        // مراقبتهم صارت نتيجة قابلة للتحويل إلى مهمة.
        $finding = $report->findings()->where('category', 'المنافسون')->firstOrFail();
        $this->assertGreaterThan(0, $finding->recommendations()->count());
    }

    private function composeCampaignReport(array $platforms)
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر تجريبي']);
        $tool = Tool::where('key', 'campaign-planner')->firstOrFail();

        $run = app(ToolRunService::class)->start($project, $tool, $user);

        // نكتب الإجابة مباشرة لعزل منطق القسم عن تحقق خطوات المعالج.
        if ($platforms !== []) {
            ToolRunAnswer::updateOrCreate(
                ['tool_run_id' => $run->id, 'field_key' => 'ad_platforms'],
                ['value_json' => ['value' => $platforms], 'source' => ToolRunAnswer::SOURCE_USER],
            );
        }

        $run = $run->fresh()->load('toolVersion.fields', 'answers');
        $answers = collect($run->answerMap())
            ->map(fn ($v) => is_array($v) && array_key_exists('value', $v) ? $v['value'] : $v)
            ->all();

        $baseline = app(DeterministicScorer::class)->score($run->toolVersion, $answers);
        $report = app(ReportComposer::class)->compose($run, $baseline, [], null, null);

        return $report->sections()->where('key', 'competitors')->first();
    }
}
