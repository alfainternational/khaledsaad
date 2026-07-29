<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Intake\IntakeCollector;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * جامع المحاور ١–٦: من كلام صاحب النشاط إلى حقائق مفهرسة بمفاتيح المحاور.
 *
 * ما يحرسه هذا الملف ليس «هل يعمل الجامع» بل الحدّ الذي يقوم عليه التسعير:
 * لا شيء يمرّ من هنا يبلغ `measured` مهما كان مصدره أو صيغته (§٥).
 */
class IntakeCollectionTest extends TestCase
{
    use RefreshDatabase;

    private IntakeCollector $collector;

    private AxisScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->collector = app(IntakeCollector::class);
        $this->scorer = app(AxisScorer::class);
    }

    #[Test]
    public function synonymous_tool_fields_land_on_one_axis_key(): void
    {
        $project = $this->emptyProject();

        // ثلاث أدوات تسأل عن التمايز بثلاثة أسماء. الدماغ يجب أن يرى قولًا
        // واحدًا لا ثلاثة، وإلا انهار التعاقب: لا شيء يُستبدل بشيء.
        $this->answer($project, 'your_edge', 'أسرع توصيل في الرياض');

        $this->collector->collect($project);

        $fact = $project->brainFacts()->where('key', 'differentiation')->first();

        $this->assertNotNull($fact, 'مفتاح الأداة الخام لم يُترجم إلى مفتاح المحور.');
        $this->assertSame('أسرع توصيل في الرياض', $fact->value_json['value']);
    }

    #[Test]
    public function the_first_available_source_wins_in_priority_order(): void
    {
        $project = $this->emptyProject();

        $this->answer($project, 'your_edge', 'الأدنى أولوية');
        $this->answer($project, 'differentiator', 'الأعلى أولوية');

        $this->collector->collect($project);

        $this->assertSame(
            'الأعلى أولوية',
            $project->brainFacts()->where('key', 'differentiation')->first()?->value_json['value'],
        );
    }

    #[Test]
    public function nothing_collected_from_intake_is_ever_measured(): void
    {
        $project = $this->emptyProject();

        $this->answer($project, 'value_proposition', 'قيمة');
        $this->answer($project, 'audience_clarity', 'documented');
        $this->answer($project, 'tracking_maturity', 'full');

        $this->collector->collect($project);

        foreach ($project->brainFacts()->where('source_module', IntakeCollector::SOURCE)->get() as $fact) {
            $this->assertSame(
                EvidenceLevel::Inferred,
                $fact->evidence_level,
                "الحقيقة {$fact->key} خرجت من الاستقبال بمستوى أقوى من inferred.",
            );
        }

        // وحتى لو امتلأ المحور كله، سقفه يمنع ترقيته.
        $this->assertSame(
            EvidenceLevel::Inferred,
            $this->scorer->score($project, Axis::MeasurementData)->evidenceLevel,
        );
    }

    #[Test]
    public function an_unrecognised_choice_is_left_out_instead_of_scored_zero(): void
    {
        $project = $this->emptyProject();

        // «لم يجب» و«أجاب بما لا نفهمه» حالتان مختلفتان: الأولى تخفض التغطية،
        // والثانية لو كُتبت لأعطت صفرًا يبدو حكمًا على نشاطه.
        $this->answer($project, 'audience_clarity', 'شيء غير معروف');

        $this->collector->collect($project);

        $this->assertNull($project->brainFacts()->where('key', 'audience_clarity')->first());
    }

    #[Test]
    public function merged_lists_do_not_count_the_same_answer_twice(): void
    {
        $project = $this->emptyProject();

        $this->answer($project, 'customer_problem', 'السعر مرتفع');
        $this->answer($project, 'main_objection', 'السعر مرتفع');
        $this->answer($project, 'friction_points', 'الشحن بطيء');

        $this->collector->collect($project);

        $this->assertSame(
            ['السعر مرتفع', 'الشحن بطيء'],
            $project->brainFacts()->where('key', 'customer_pains')->first()?->value_json['value'],
        );
    }

    #[Test]
    public function the_axis_score_becomes_reproducible_from_answers_alone(): void
    {
        $project = $this->emptyProject();

        // المحور ٥ أوزانه: analytics_connected(3) + conversion_tracking(3)
        // + reporting_rhythm(1) = 7
        // نُرضي: analytics_connected كاملًا (3) + tracking_maturity=basic → 3×0.5 = 1.5
        // المجموع 4.5 من 7 = 64.28٪ → 64
        $this->answer($project, 'tracking_setup', 'Google Analytics 4');
        $this->answer($project, 'tracking_maturity', 'basic');

        $this->collector->collect($project);
        $score = $this->scorer->score($project, Axis::MeasurementData);

        $this->assertSame(64, $score->score);
        $this->assertEqualsWithDelta(0.6667, $score->coverage, 0.0001);
        $this->assertContains('إيقاع المراجعة', $score->gaps);
    }

    #[Test]
    public function a_vanished_answer_is_retracted_not_left_standing(): void
    {
        $project = $this->emptyProject();

        $this->answer($project, 'differentiator', 'ميزة قديمة');
        $this->collector->collect($project);

        ProjectAnswer::where('project_id', $project->id)->delete();
        $this->collector->collect($project);

        // الحقيقة تبقى في السجل ولا تعود تُحسب — التراجع معلومة لا فراغ.
        $this->assertSame(1, $project->brainFacts()->where('key', 'differentiation')->count());
        $this->assertNotNull(
            $project->brainFacts()->where('key', 'differentiation')->first()?->retracted_at,
        );
        $this->assertSame(0.0, $this->scorer->score($project, Axis::PositioningMessage)->coverage);
    }

    #[Test]
    public function a_collector_does_not_retract_what_another_module_measured(): void
    {
        $project = $this->emptyProject();

        // مفتاح كتبه جامع مستقل. صمت الاستقبال عنه ليس تراجعًا عنه.
        app(BrainWriter::class)->record(
            $project, 'differentiation', 'قياس مستقل', EvidenceLevel::Measured, 'AiReadiness',
        );

        $this->collector->collect($project);

        $fact = $project->brainFacts()->where('key', 'differentiation')->first();

        $this->assertNull($fact?->retracted_at);
        $this->assertSame(EvidenceLevel::Measured, $fact?->evidence_level);
    }

    #[Test]
    public function two_sources_stating_the_same_value_is_agreement_not_conflict(): void
    {
        $project = $this->emptyProject();

        app(BrainWriter::class)->record(
            $project, 'geography', 'الرياض', EvidenceLevel::Inferred, 'Profile',
        );

        $this->answer($project, 'geography', 'الرياض');
        $this->collector->collect($project);

        $this->assertSame(0, BrainEvent::query()
            ->where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_FACT_CONFLICT)
            ->count());
        $this->assertSame(1, $project->brainFacts()->where('key', 'geography')->count());
    }

    private function answer(Project $project, string $key, mixed $value): void
    {
        ProjectAnswer::updateOrCreate(
            ['project_id' => $project->id, 'field_key' => $key],
            ['value_json' => ['value' => $value]],
        );
    }

    private function emptyProject(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الجمع']);

        $project->brainFacts()->delete();
        ProjectAnswer::where('project_id', $project->id)->delete();

        return $project->fresh();
    }
}
