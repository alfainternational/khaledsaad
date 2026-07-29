<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ProjectSnapshotBuilder;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الجسر موصول: ما يُولَّد يقرأ التشخيص.
 *
 * قبل هذا كان `LegacyCapabilities` مبنيًّا بلا مستدعٍ واحد — أي أن الخطط
 * تُولَّد من وصف المستخدم لنفسه، وهو بالضبط ما تمنعه §٢: التوليد مجاني
 * ومتاح، وقيمته الوحيدة أنه يستهلك قياسًا سبقه.
 */
class PlatformBridgeWiringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ToolCatalogSeeder::class);
    }

    #[Test]
    public function a_measured_project_hands_its_diagnosis_to_the_generator(): void
    {
        $project = $this->project();

        app(BrainWriter::class)->record(
            $project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness',
        );

        $snapshot = app(ProjectSnapshotBuilder::class)->build($this->newRun($project));

        $this->assertArrayHasKey('diagnosis', $snapshot);
        $this->assertArrayHasKey(MetricKey::MATURITY_SCORE, $snapshot['diagnosis']);
        $this->assertGreaterThan(0, $snapshot['diagnosis']['axes_active']);
    }

    #[Test]
    public function an_unmeasured_project_gets_no_diagnosis_section_at_all(): void
    {
        $snapshot = app(ProjectSnapshotBuilder::class)->build($this->newRun($this->project()));

        // لا قسم فارغ ولا أصفار: القسم الغائب يعني «لم يُقَس»، والقسم بأصفار
        // يعني «قِيس فكان صفرًا» — والنموذج يبني على الثاني توصيات كاذبة.
        $this->assertArrayNotHasKey('diagnosis', $snapshot);
    }

    #[Test]
    public function only_measured_axes_travel_to_the_generator(): void
    {
        $project = $this->project();
        $brain = app(BrainWriter::class);

        $brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');

        $snapshot = app(ProjectSnapshotBuilder::class)->build($this->newRun($project));
        $axes = collect($snapshot['diagnosis']['axes']);

        $this->assertTrue($axes->every(fn (array $axis) => $axis['active'] === true));
        $this->assertSame($snapshot['diagnosis']['axes_active'], $axes->count());
    }

    private function project(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع الجسر',
            'description' => 'متجر إلكتروني لمستلزمات القهوة المختصة.',
            'business_model' => 'ecommerce',
        ]);

        $project->brainFacts()->delete();

        return $project->fresh();
    }

    private function newRun(Project $project): ToolRun
    {
        return app(ToolRunService::class)->start(
            $project,
            Tool::where('key', 'campaign-planner')->firstOrFail(),
            $project->workspace->owner,
        );
    }
}
