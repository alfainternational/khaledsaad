<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\Brain\BrainReader;
use App\Modules\Brain\BrainWriter;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Brain\Models\BrainFact;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * سجل الدماغ: التعاقب والتعارض والتغطية.
 *
 * هذه القواعد هي ما يفرّق الدماغ عن جدول إعدادات. الاختبار ببيانات ثابتة
 * لأن السلوك يجب أن يكون قابلًا لإعادة الإنتاج حرفيًا.
 */
class BrainLedgerTest extends TestCase
{
    use RefreshDatabase;

    private BrainWriter $writer;

    private BrainReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(BrainWriter::class);
        $this->reader = app(BrainReader::class);
    }

    #[Test]
    public function repeating_the_same_value_from_the_same_source_does_not_create_history(): void
    {
        $project = $this->project();

        $first = $this->writer->record($project, 'monthly_budget', 10000, EvidenceLevel::Inferred, 'Intake');
        $again = $this->writer->record($project, 'monthly_budget', 10000, EvidenceLevel::Inferred, 'Intake');

        // إعادة تأكيد ليست تغييرًا: لو أنشأنا صفًا لكل تكرار لامتلأ التاريخ
        // بضجيج يخفي التغيّرات الحقيقية التي يقوم عليها التنبيه.
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, BrainFact::where('project_id', $project->id)
            ->where('key', 'monthly_budget')->count());
    }

    #[Test]
    public function a_changed_value_supersedes_instead_of_overwriting(): void
    {
        $project = $this->project();

        $old = $this->writer->record($project, 'monthly_budget', 10000, EvidenceLevel::Inferred, 'Intake');
        $new = $this->writer->record($project, 'monthly_budget', 25000, EvidenceLevel::Inferred, 'Intake');

        $this->assertNotSame($old->id, $new->id);
        $this->assertSame($new->id, $old->fresh()->superseded_by);

        // السارية واحدة، والتاريخ محفوظ كاملًا.
        $this->assertSame(1, BrainFact::where('project_id', $project->id)
            ->where('key', 'monthly_budget')->active()->count());
        $this->assertCount(2, $this->reader->history($project, 'monthly_budget'));
        $this->assertSame(25000, $this->reader->value($project, 'monthly_budget'));
    }

    #[Test]
    public function two_sources_disagreeing_keeps_both_and_flags_a_conflict(): void
    {
        $project = $this->project();

        $stated = $this->writer->record($project, 'monthly_traffic', 5000, EvidenceLevel::Inferred, 'Intake');
        $measured = $this->writer->record($project, 'monthly_traffic', 900, EvidenceLevel::Measured, 'AiReadiness');

        // لا نحسم أي المصدرين أصدق: أن يقول النشاط ٥٠٠٠ وتقول بياناته ٩٠٠
        // معلومة بحد ذاتها، وحسمها صامتًا يمحوها.
        $this->assertNull($stated->fresh()->superseded_by);
        $this->assertNull($measured->fresh()->superseded_by);

        // العدّ على المفتاح لا على المشروع: إنشاء المشروع نفسه يكتب حقائق ملفه.
        $this->assertSame(2, BrainFact::where('project_id', $project->id)
            ->where('key', 'monthly_traffic')->active()->count());

        $conflicts = $this->reader->openConflicts($project);
        $this->assertCount(1, $conflicts);
        $this->assertSame(BrainEvent::TYPE_FACT_CONFLICT, $conflicts->first()->type);
        $this->assertSame('Intake', $conflicts->first()->body['existing_source']);
        $this->assertSame('AiReadiness', $conflicts->first()->body['incoming_source']);
    }

    #[Test]
    public function coverage_reports_missing_keys_instead_of_filling_them(): void
    {
        $project = $this->project();

        $this->writer->record($project, 'value_proposition', 'شحن أسرع', EvidenceLevel::Inferred, 'Intake');

        $coverage = $this->reader->coverage($project, ['value_proposition', 'geography', 'business_model']);

        $this->assertSame(1, $coverage['known']);
        $this->assertSame(3, $coverage['required']);
        $this->assertSame(0.3333, $coverage['ratio']);
        $this->assertEqualsCanonicalizing(['geography', 'business_model'], $coverage['missing']);
    }

    #[Test]
    public function an_output_built_on_one_assumption_is_never_measured(): void
    {
        $project = $this->project();

        $this->writer->record($project, 'schema_present', true, EvidenceLevel::Measured, 'AiReadiness');
        $this->writer->record($project, 'target_segment', 'أمهات جدد', EvidenceLevel::Inferred, 'Intake');

        // الأضعف يحكم: مخرج يخلط قياسًا بفرضية يبقى فرضية.
        $this->assertSame(
            EvidenceLevel::Inferred,
            $this->reader->evidenceLevelFor($project, ['schema_present', 'target_segment']),
        );

        $this->assertSame(
            EvidenceLevel::Measured,
            $this->reader->evidenceLevelFor($project, ['schema_present']),
        );

        // مفتاح غائب لا يُعامل كمؤكد.
        $this->assertSame(
            EvidenceLevel::Inferred,
            $this->reader->evidenceLevelFor($project, ['schema_present', 'never_recorded']),
        );
    }

    #[Test]
    public function a_snapshot_freezes_the_state_a_diagnosis_was_built_on(): void
    {
        $project = $this->project();

        $this->writer->record($project, 'monthly_budget', 10000, EvidenceLevel::Inferred, 'Intake');
        $snapshot = $this->reader->takeSnapshot($project);

        // تغيّر لاحق لا يمسّ اللقطة، وإلا استحال شرح درجة قديمة.
        $this->writer->record($project, 'monthly_budget', 25000, EvidenceLevel::Inferred, 'Intake');

        $this->assertSame(10000, $snapshot->fresh()->payload['monthly_budget']['value']);
        $this->assertSame('inferred', $snapshot->fresh()->payload['monthly_budget']['evidence_level']);
        $this->assertSame(25000, $this->reader->value($project, 'monthly_budget'));
    }

    private function project(): Project
    {
        $user = User::factory()->create();

        return app(ProjectService::class)->create($user, [
            'name' => 'متجر تجريبي',
            'industry' => 'تجزئة',
            'stage' => 'growth',
        ]);
    }
}
