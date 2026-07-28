<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\AiReadiness\ReadinessCollector;
use App\Modules\Brain\BrainReader;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * الوصل بين الجمع والحساب: ما يُرصد يصير حقيقة، والحقيقة تصير درجة.
 *
 * هذه هي السلسلة التي تجعل المحور ٧ قابلًا للبيع — رقم لا يمرّ بوصف صاحب
 * النشاط لموقعه في أي نقطة منها.
 */
class ReadinessCollectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function what_the_audit_observes_becomes_a_measured_fact_and_then_a_score(): void
    {
        $project = $this->project();

        $this->collector([
            '/' => '<html lang="ar" dir="rtl"><h1>متجر</h1><h2>منتجات</h2>'
                .'<script type="application/ld+json">{"@type":"Organization"}</script>'
                .'<a href="/p">سياسة الخصوصية</a><a href="/s">الشحن</a><a href="/t">الشروط</a></html>',
        ])->collectSiteAudit($project, 'https://example.test');

        $reader = app(BrainReader::class);
        $fact = $reader->fact($project, 'schema_organization');

        $this->assertNotNull($fact);
        $this->assertSame(EvidenceLevel::Measured, $fact->evidence_level);
        $this->assertSame('AiReadiness', $fact->source_module);

        // والمحور يقرأها فيرتفع بها، ويبقى measured لأن مصدرها مستقل.
        $score = app(AxisScorer::class)->score($project->fresh(), Axis::AiReadiness);
        $this->assertGreaterThan(0, $score->score);
        $this->assertSame(EvidenceLevel::Measured, $score->evidenceLevel);
        $this->assertFalse($score->evidenceLevel->needsAssumptionBadge());
    }

    #[Test]
    public function an_unreachable_site_writes_nothing_so_coverage_shows_the_truth(): void
    {
        $project = $this->project();

        $result = $this->collector([])->collectSiteAudit($project, 'https://down.test');

        $this->assertFalse($result->reachable);

        /*
         * لو كتبنا صفرًا لصار انقطاع الاتصال درجةً منخفضة يطاردها صاحب
         * النشاط. التغطية — لا الدرجة — هي ما يجب أن يقول إننا لم نفحص.
         */
        $score = app(AxisScorer::class)->score($project->fresh(), Axis::AiReadiness);
        $this->assertSame(0.0, $score->coverage);
        $this->assertFalse($score->isActive());
    }

    #[Test]
    public function an_unreadable_crawl_log_does_not_claim_zero_visits(): void
    {
        $project = $this->project();

        $summary = $this->collector([])->collectCrawlLog($project, "ضجيج\nنص بلا معنى");

        $this->assertSame(0, $summary['parsed_lines']);

        // «صفر زيارة» من ملف لم يُقرأ يصف الملف لا الموقع.
        $this->assertNull(app(BrainReader::class)->fact($project, 'ai_bot_visits_30d'));
    }

    #[Test]
    public function a_readable_crawl_log_records_the_visit_count_with_its_period(): void
    {
        $project = $this->project();
        $stamp = now()->subDays(2)->format('d/M/Y:H:i:s O');
        $log = sprintf(
            '66.249.66.1 - - [%s] "GET / HTTP/1.1" 200 12 "-" "GPTBot/1.1"',
            $stamp,
        );

        $this->collector([])->collectCrawlLog($project, $log);

        $fact = app(BrainReader::class)->fact($project, 'ai_bot_visits_30d');

        $this->assertNotNull($fact);
        $this->assertSame(1, $fact->value_json['value']);
        $this->assertSame(EvidenceLevel::Measured, $fact->evidence_level);

        // النافذة تُحفظ مع الرقم: «١ زيارة» بلا مداها لا معنى له (§١٣).
        $this->assertNotNull($fact->period);
        $this->assertStringContainsString('..', $fact->period);
    }

    /**
     * @param  array<string, string>  $pages
     */
    private function collector(array $pages): ReadinessCollector
    {
        $this->app->bind(PageFetcher::class, fn () => new class($pages) implements PageFetcher
        {
            /** @param array<string, string> $pages */
            public function __construct(private readonly array $pages) {}

            public function get(string $url): ?string
            {
                $path = parse_url($url, PHP_URL_PATH);

                return $this->pages[$path === null || $path === '' ? '/' : $path] ?? null;
            }
        });

        return app(ReadinessCollector::class);
    }

    private function project(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر التدقيق']);
        $project->brainFacts()->delete();

        return $project->fresh();
    }
}
