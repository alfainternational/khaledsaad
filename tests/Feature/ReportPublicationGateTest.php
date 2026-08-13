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
use App\Modules\Reporting\Publication\Provenance;
use App\Modules\Reporting\Publication\ReportPublicationGate;
use App\Modules\Reporting\Templates\TemplateResolver;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportPublicationGateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_publishes_only_after_storing_a_valid_immutable_contract(): void
    {
        $report = $this->validReport();

        $published = app(ReportPublicationGate::class)->publish($report);

        $this->assertSame('published', $published->status);
        $this->assertSame(2, $published->schema_version);
        $this->assertNotNull($published->issued_at);
        $this->assertEquals(50.0, data_get($published->contract_payload, 'score.value'));
        $this->assertSame('متجر أفق', data_get($published->contract_payload, 'findings.0.recommendation.template.blocks.0.value'));
        $this->assertContains($published->validation_status, ['passed', 'passed_with_warnings']);
    }

    #[Test]
    public function it_preserves_blocking_findings_when_publication_is_rejected(): void
    {
        $report = $this->validReport();
        $report->forceFill(['score' => 77])->save();

        try {
            app(ReportPublicationGate::class)->publish($report);
            $this->fail('Expected the publication gate to reject the inconsistent score.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('R11', $exception->getMessage());
        }

        $report->refresh();
        $this->assertSame('draft', $report->status);
        $this->assertSame('failed', $report->validation_status);
        $this->assertDatabaseHas('validation_findings', [
            'report_id' => $report->id,
            'rule_code' => 'R11',
            'severity' => 'block',
        ]);
    }

    #[Test]
    public function it_rejects_a_signed_report_without_a_recorded_human_trace(): void
    {
        $report = $this->validReport();
        $report->forceFill(['provenance' => Provenance::Signed->value])->save();

        $this->expectException(ValidationException::class);

        try {
            app(ReportPublicationGate::class)->publish($report);
        } finally {
            $this->assertDatabaseHas('validation_findings', [
                'report_id' => $report->id,
                'rule_code' => 'R13',
                'severity' => 'block',
            ]);
        }
    }

    private function validReport(): Report
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر أفق']);
        $tool = Tool::where('key', 'marketing-score')->firstOrFail();
        $run = app(ToolRunService::class)->start($project, $tool, $user);
        $run->forceFill(['status' => ToolRun::STATUS_COMPLETED, 'base_score' => 50])->save();
        $answer = $run->answers()->create([
            'field_key' => 'tracking_maturity',
            'value_json' => ['نقيس الزيارات فقط'],
            'source' => 'user',
        ]);
        $objective = Objective::where('slug', 'establish-measurement-baseline')->firstOrFail();
        $template = RecommendationTemplate::where('objective_id', $objective->id)->firstOrFail();
        $resolvedTemplate = app(TemplateResolver::class)->forObjective($objective->slug, [
            'project' => ['name' => $project->name],
        ])?->toArray();
        $report = Report::create([
            'tool_run_id' => $run->id,
            'project_id' => $project->id,
            'title' => 'تقرير القياس',
            'status' => 'draft',
            'score' => 50,
            'score_raw' => 50,
            'score_max' => 100,
            'score_band' => Report::bandFor(50),
            'summary' => 'القياس الحالي لا يصل إلى الإيراد.',
            'next_step' => ['title' => 'ثبّت خط القياس', 'description' => 'اجمع خط أساس واحدًا.'],
            'provenance' => Provenance::Automated->value,
        ]);
        $report->scoringItems()->create([
            'item_key' => 'tracking_maturity',
            'weight' => 100,
            'coefficient' => .5,
            'points' => 50,
            'answer_value' => ['نقيس الزيارات فقط'],
            'answer_quote' => 'نقيس الزيارات فقط',
        ]);
        $finding = Finding::create([
            'report_id' => $report->id,
            'category' => 'القياس',
            'title' => 'القياس لا يصل إلى الإيراد',
            'description' => 'التتبع الحالي يصف الزيارات ولا يثبت القناة التي أنتجت الطلب.',
            'severity' => 'high',
            'evidence' => 'نقيس الزيارات فقط',
            'evidence_answer_id' => $answer->id,
            'evidence_quote' => 'نقيس الزيارات فقط',
            'confidence' => 90,
            'is_assumption' => false,
            'sort_order' => 0,
        ]);
        Recommendation::create([
            'finding_id' => $finding->id,
            'report_id' => $report->id,
            'objective_id' => $objective->id,
            'metric_objective_id' => $objective->id,
            'template_id' => $template->id,
            'template_payload' => $resolvedTemplate,
            'title' => 'ثبّت خط أساس أسبوعيًا',
            'description' => 'قيمة البداية تجعل تغير الأداء قابلًا للمقارنة.',
            'deliverable' => 'جدول خط أساس للأسبوع الأول',
            'done_when' => 'يحتوي الجدول على المؤشر والقيمة والمصدر والتاريخ والمسؤول.',
            'first_five_minutes' => 'افتح ورقة واكتب اسم المؤشر ومصدره في الصف الأول.',
            'expected_failure' => 'قد تبحث عن أداة جديدة؛ استخدم المصدر المتاح وسجل قصوره.',
            'action_steps' => [
                'اكتب قيمة المؤشر الحالية ومصدرها وتاريخ جمعها في صف واحد.',
                'حدد المسؤول وموعد تحديث القيمة نفسها في الأسبوع القادم.',
            ],
            'impact' => 'high',
            'effort' => 'low',
            'duration_days' => 3,
            'priority' => 90,
            'kpi_hint' => 'اكتمال خط الأساس الأسبوعي',
        ]);

        return $report;
    }
}
