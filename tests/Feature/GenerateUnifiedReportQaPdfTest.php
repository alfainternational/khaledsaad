<?php

namespace Tests\Feature;

use App\Models\Finding;
use App\Models\Objective;
use App\Models\Recommendation;
use App\Models\RecommendationTemplate;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Reporting\ReportPdfGenerator;
use App\Modules\Reporting\Templates\TemplateResolver;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateUnifiedReportQaPdfTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function generate_visual_qa_fixture(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر أفق']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 58])->save();
        $report = Report::create([
            'tool_run_id' => $run->id, 'project_id' => $project->id,
            'title' => 'التشخيص التسويقي الموحد — متجر أفق', 'status' => 'published',
            'score' => 58, 'score_raw' => 58, 'score_max' => 100, 'score_band' => 'يحتاج ترتيبًا',
            'summary' => 'يوجد عرض قابل للنمو، لكن القياس وصفحة التحويل يحتاجان إلى ترتيب قبل زيادة الإنفاق.',
            'next_step' => ['title' => 'ثبّت خط القياس', 'description' => 'اجمع قيمة بداية واحدة ومصدرها وتاريخها قبل تغيير الحملة.'],
            'provenance' => 'automated', 'validation_status' => 'passed', 'schema_version' => 2,
        ]);
        $finding = Finding::create([
            'report_id' => $report->id, 'category' => 'القياس', 'title' => 'القياس لا يصل إلى الإيراد',
            'description' => 'التتبع الحالي يصف الزيارات ولا يثبت أي قناة أنتجت طلبًا أو شراءً.',
            'severity' => 'high', 'evidence' => 'ذكرت أن القياس يقتصر على الزيارات.',
            'confidence' => 90, 'is_assumption' => false, 'sort_order' => 0,
        ]);
        $objective = Objective::where('slug', 'establish-measurement-baseline')->firstOrFail();
        $template = RecommendationTemplate::where('objective_id', $objective->id)->firstOrFail();
        $resolvedTemplate = app(TemplateResolver::class)->forObjective($objective->slug, [
            'project' => ['name' => $project->name],
        ])?->toArray();
        Recommendation::create([
            'finding_id' => $finding->id, 'report_id' => $report->id,
            'objective_id' => $objective->id, 'metric_objective_id' => $objective->id,
            'template_id' => $template->id,
            'template_payload' => $resolvedTemplate,
            'title' => 'ثبّت خط أساس أسبوعيًا', 'description' => 'اجمع قيمة البداية نفسها كل أسبوع من مصدر واحد.',
            'deliverable' => 'جدول خط أساس للأسبوع الأول',
            'done_when' => 'يحتوي الجدول على المؤشر والقيمة والمصدر والتاريخ والمسؤول.',
            'first_five_minutes' => 'افتح ورقة جديدة واكتب اسم المؤشر ومصدره في الصف الأول.',
            'expected_failure' => 'قد تبحث عن أداة جديدة؛ استخدم المصدر المتاح اليوم وسجل قصوره.',
            'action_steps' => ['اكتب قيمة المؤشر الحالية ومصدرها وتاريخ جمعها في صف واحد.', 'حدّد المسؤول وموعد تحديث القيمة نفسها في الأسبوع القادم.'],
            'impact' => 'high', 'effort' => 'low', 'duration_days' => 3, 'priority' => 90,
            'kpi_hint' => 'اكتمال خط الأساس الأسبوعي',
        ]);

        $stored = app(ReportPdfGenerator::class)->generate($report);
        $source = storage_path('app/private/'.$stored);
        $target = base_path('output/pdf/unified-report-contract-qa.pdf');
        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }
        copy($source, $target);

        $this->assertFileExists($target);
        $this->assertGreaterThan(1000, filesize($target));
    }
}
