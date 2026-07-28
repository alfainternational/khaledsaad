<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\BrainWriter;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Diagnosis\MaturityAggregator;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * محرك المحاور: درجة حتمية بأسماء §١٢ حرفيًّا.
 *
 * كل تأكيد هنا ببيانات ثابتة ورقم محسوب يدويًّا. الدرجة التي لا يمكن شرحها
 * بحساب مكتوب لا يمكن الدفاع عنها أمام صاحب النشاط.
 */
class AxisScoringTest extends TestCase
{
    use RefreshDatabase;

    private BrainWriter $brain;

    private AxisScorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->brain = app(BrainWriter::class);
        $this->scorer = app(AxisScorer::class);
    }

    #[Test]
    public function an_axis_with_no_inputs_is_uncalculated_not_zero(): void
    {
        $project = $this->emptyProject();
        $result = $this->scorer->score($project, Axis::AiReadiness);

        $this->assertSame(0, $result->score);
        $this->assertSame(0.0, $result->coverage);

        // الفرق الحاسم: محور لم يُقَس لا يدخل المتوسط، فلا يخفض الدرجة الكلية.
        $this->assertFalse($result->isActive());
    }

    #[Test]
    public function the_axis_score_is_the_weighted_share_of_satisfied_inputs(): void
    {
        $project = $this->emptyProject();

        // المحور ٧ أوزانه: 3+2+2+2+2+1+3+2 = 17
        // نُرضي: schema_organization(3) + ai_bots_allowed(3) = 6
        // و arabic_page_structure = partial → 2 × 0.5 = 1
        // المجموع 7 من 17 = 41.18٪ → 41
        $this->brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');
        $this->brain->record($project, 'ai_bots_allowed', true, EvidenceLevel::Measured, 'AiReadiness');
        $this->brain->record($project, 'arabic_page_structure', 'partial', EvidenceLevel::Measured, 'AiReadiness');

        $result = $this->scorer->score($project, Axis::AiReadiness);

        $this->assertSame(41, $result->score);
        $this->assertSame(0.375, $result->coverage, '٣ مدخلات معروفة من ٨.');
        $this->assertTrue($result->isActive());
    }

    #[Test]
    public function a_partially_satisfied_input_still_counts_as_a_gap(): void
    {
        $project = $this->emptyProject();
        $this->brain->record($project, 'arabic_page_structure', 'partial', EvidenceLevel::Measured, 'AiReadiness');

        $result = $this->scorer->score($project, Axis::AiReadiness);

        // «موجود لكن ناقص» فجوة أيضًا، وإلا اختفى نصف قائمة الإصلاح.
        $this->assertContains('بنية الصفحات العربية', $result->gaps);
        $this->assertContains('ملف llms.txt', $result->gaps, 'الغائب فجوة كذلك.');
    }

    #[Test]
    public function counted_inputs_scale_toward_their_target(): void
    {
        $project = $this->emptyProject();

        // policy_pages هدفه ٣: صفحتان = 0.67 من وزنه.
        $this->brain->record($project, 'policy_pages', ['الخصوصية', 'الاستبدال'], EvidenceLevel::Measured, 'AiReadiness');

        $result = $this->scorer->score($project, Axis::AiReadiness);
        $row = collect($result->breakdown)->firstWhere('label', 'صفحات السياسات');

        $this->assertSame(0.67, $row['factor']);
    }

    #[Test]
    public function a_stated_axis_never_reaches_measured_even_if_its_facts_claim_to_be(): void
    {
        $project = $this->emptyProject();

        // حتى لو كتبت وحدة ما حقيقة measured تحت مفتاح استراتيجي، يبقى
        // المحور inferred: مصدره بطبيعته كلام صاحب النشاط عن نفسه (§١٥).
        $this->brain->record($project, 'value_proposition', 'شحن أسرع', EvidenceLevel::Measured, 'Intake');
        $this->brain->record($project, 'primary_goal', 'مبيعات', EvidenceLevel::Measured, 'Intake');

        $result = $this->scorer->score($project, Axis::StrategicClarity);

        $this->assertSame(EvidenceLevel::Inferred, $result->evidenceLevel);
        $this->assertTrue($result->evidenceLevel->needsAssumptionBadge());
    }

    #[Test]
    public function a_measured_axis_drops_to_inferred_when_one_input_is_a_guess(): void
    {
        $project = $this->emptyProject();
        $this->brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');
        $this->brain->record($project, 'ai_bots_allowed', true, EvidenceLevel::Inferred, 'AiReadiness');

        $result = $this->scorer->score($project, Axis::AiReadiness);

        $this->assertSame(EvidenceLevel::Inferred, $result->evidenceLevel, 'الأضعف يحكم.');
    }

    #[Test]
    public function maturity_averages_only_the_axes_that_were_actually_measured(): void
    {
        $project = $this->emptyProject();

        // محور ٧ وحده: 3+3 من 17 = 35.29 → 35. ووزنه 1.5، وهو الوحيد المفعّل.
        $this->brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');
        $this->brain->record($project, 'ai_bots_allowed', true, EvidenceLevel::Measured, 'AiReadiness');

        $result = app(MaturityAggregator::class)->compute($project);

        $this->assertSame(35, $result[MetricKey::MATURITY_SCORE]);
        $this->assertSame(1, $result['axes_active']);
        $this->assertSame(8, $result['axes_total']);
        $this->assertSame(0.125, $result['score_coverage'], 'الرقم يُعرض مع أساسه.');
    }

    #[Test]
    public function a_snapshot_is_frozen_with_every_score(): void
    {
        $project = $this->emptyProject();
        $this->brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');

        $result = app(MaturityAggregator::class)->computeAndSnapshot($project);

        // بلا لقطة يستحيل شرح درجة قديمة بعد أن تتغير الحقائق.
        $this->assertArrayHasKey('brain_snapshot_id', $result);
        $this->assertDatabaseHas('brain_snapshots', ['id' => $result['brain_snapshot_id']]);
    }

    #[Test]
    public function the_same_facts_always_produce_the_same_score(): void
    {
        $project = $this->emptyProject();
        $this->brain->record($project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness');
        $this->brain->record($project, 'policy_pages', ['أ', 'ب', 'ج'], EvidenceLevel::Measured, 'AiReadiness');

        $first = $this->scorer->score($project, Axis::AiReadiness);
        $second = $this->scorer->score($project, Axis::AiReadiness);

        // الحتمية شرط المقارنة الزمنية، وعليها يقوم التنبيه.
        $this->assertSame($first->score, $second->score);
        $this->assertSame($first->coverage, $second->coverage);
    }

    /**
     * مشروع بلا حقائق ملف: ProjectService يكتب حقائق عند الإنشاء، فتُسحب هنا
     * كي تبقى الأرقام محسوبة يدويًّا لا متأثرة ببذور جانبية.
     */
    private function emptyProject(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع الحساب']);

        $project->brainFacts()->delete();

        return $project->fresh();
    }
}
