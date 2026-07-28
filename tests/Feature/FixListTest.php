<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\AiReadiness\SiteAudit;
use App\Modules\Brain\BrainWriter;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\FixList;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * قائمة الإصلاح: ما أعمله أولًا.
 *
 * المخرج الذي لا يقود إلى قرار يُحذف. هذه الاختبارات تحرس أن الترتيب يقود
 * فعلًا، وأن المستوى ٠ لا يسرّب الحل.
 */
class FixListTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function higher_impact_comes_first_and_ties_break_toward_less_effort(): void
    {
        $project = $this->project();
        $fixes = app(FixList::class)->build($project, [Axis::AiReadiness]);

        $impacts = array_column($fixes, 'impact');
        $sorted = $impacts;
        rsort($sorted);
        $this->assertSame($sorted, $impacts, 'الأثر الأعلى أولًا.');

        // عند تساوي الأثر (وزن ٣ لكليهما) يتقدّم الأقل جهدًا.
        $topThree = array_slice($fixes, 0, 3);
        $keys = array_column($topThree, 'key');
        $this->assertContains('schema_organization', $keys);
        $this->assertLessThan(
            array_search('schema_products', array_column($fixes, 'key'), true),
            array_search('schema_organization', array_column($fixes, 'key'), true),
            'المتساويان في الأثر يرتّبهما الجهد.',
        );
    }

    #[Test]
    public function a_satisfied_input_leaves_the_list(): void
    {
        $project = $this->project();
        app(BrainWriter::class)->record(
            $project, 'llms_txt', true, EvidenceLevel::Measured, 'AiReadiness',
        );

        $keys = array_column(app(FixList::class)->build($project->fresh(), [Axis::AiReadiness]), 'key');

        $this->assertNotContains('llms_txt', $keys);
    }

    #[Test]
    public function each_item_carries_its_reason_and_its_repair_when_an_audit_is_attached(): void
    {
        $project = $this->project();
        $audit = $this->audit();

        $fixes = app(FixList::class)->build($project, [Axis::AiReadiness], $audit);
        $item = collect($fixes)->firstWhere('key', 'schema_organization');

        // «درجتك ٤١» ليست قرارًا. «أضف JSON-LD من نوع Organization» قرار.
        $this->assertNotNull($item['why']);
        $this->assertStringContainsString('JSON-LD', $item['fix']);
    }

    #[Test]
    public function items_from_a_stated_axis_stay_marked_as_assumptions(): void
    {
        $project = $this->project();
        app(BrainWriter::class)->record(
            $project, 'value_proposition', 'قيمة', EvidenceLevel::Inferred, 'Intake',
        );

        $fixes = app(FixList::class)->build($project->fresh(), [Axis::StrategicClarity]);

        // فجوة في محور استنتاجي رأي منهجي لا عيب مرصود. بلا الوسم يصلح
        // المستخدم ما لم نتأكد أنه مكسور.
        $this->assertNotEmpty($fixes);
        foreach ($fixes as $fix) {
            $this->assertTrue($fix['is_assumption']);
        }
    }

    #[Test]
    public function measured_items_are_not_marked_as_assumptions(): void
    {
        $project = $this->project();
        app(BrainWriter::class)->record(
            $project, 'schema_organization', true, EvidenceLevel::Measured, 'AiReadiness',
        );

        $fixes = app(FixList::class)->build($project->fresh(), [Axis::AiReadiness]);

        $this->assertNotEmpty($fixes);
        foreach ($fixes as $fix) {
            $this->assertFalse($fix['is_assumption']);
        }
    }

    #[Test]
    public function the_free_tier_names_the_gap_without_handing_over_the_fix(): void
    {
        $project = $this->project();
        $teaser = app(FixList::class)->teaser($project, [Axis::AiReadiness]);

        $this->assertCount(3, $teaser, 'ثلاث فجوات بالضبط، لا أكثر.');

        /*
         * التجريد صريح لا مخفي في الواجهة: أي مسار يقرأ هذا المخرج — ويب أو
         * تطبيق أو API — لا يستطيع تسريب الحل حتى لو أراد.
         */
        foreach ($teaser as $item) {
            $this->assertArrayNotHasKey('fix', $item);
            $this->assertArrayNotHasKey('why', $item);
            $this->assertArrayHasKey('title', $item);
        }
    }

    private function audit(): \App\Modules\AiReadiness\SiteAuditResult
    {
        $fetcher = new class implements PageFetcher
        {
            public function get(string $url): ?string
            {
                return str_contains($url, 'robots') ? null : '<html></html>';
            }
        };

        return (new SiteAudit($fetcher))->audit('https://example.test');
    }

    private function project(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'قائمة الإصلاح']);
        $project->brainFacts()->delete();

        return $project->fresh();
    }
}
