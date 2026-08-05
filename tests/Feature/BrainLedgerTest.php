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

        // الحدث نفسه في السجل.
        $this->assertSame(1, BrainEvent::query()
            ->where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_FACT_CONFLICT)
            ->count());

        /*
         * ويُقرأ عبر الواجهة التي تصل المستخدم فعلًا: التعارض المسجَّل ولا
         * يراه أحد لا يُنفّذ §٩ — «يُعلَّم للمراجعة» تعني أن تصل المراجعةَ.
         */
        $conflicts = $this->reader->openConflictsWithValues($project);

        $this->assertCount(1, $conflicts);
        $this->assertSame('monthly_traffic', $conflicts[0]['key']);
        $this->assertEqualsCanonicalizing(
            ['Intake', 'AiReadiness'],
            array_column($conflicts[0]['sides'], 'source'),
        );
        $this->assertEqualsCanonicalizing(
            [5000, 900],
            array_column($conflicts[0]['sides'], 'value'),
        );
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
