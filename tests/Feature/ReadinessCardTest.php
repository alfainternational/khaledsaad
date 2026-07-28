<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\AiReadiness\CrawlLogAnalyzer;
use App\Modules\AiReadiness\SiteAudit;
use App\Modules\AiReadiness\SiteAuditResult;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Diagnosis\FixList;
use App\Modules\Reporting\ReadinessCardPdfGenerator;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * بطاقة الجاهزية: مخرج المرحلة ١ المباع.
 *
 * نتحقق من قالب الطباعة (HTML قبل التحويل) لأن الـPDF ثنائي مضغوط. لو حُذف
 * بند أو تغيّر معنى حالة، يسقط الاختبار.
 */
class ReadinessCardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_card_shows_its_score_with_the_basis_of_that_score(): void
    {
        $html = $this->render($this->goodSite());

        $this->assertStringContainsString('بطاقة الجاهزية للذكاء الاصطناعي', $html);

        // رقم بلا أساسه يخالف §١٣: القارئ لا يعرف إن كانت ٤١ من فحص كامل أم ناقص.
        $this->assertStringContainsString('تغطية', $html);
        $this->assertStringContainsString('بندًا من', $html);
    }

    #[Test]
    public function it_declares_that_the_score_is_measured_not_self_reported(): void
    {
        $html = $this->render($this->goodSite());

        // هذا ما يفرّق البطاقة عن تقرير مبنيّ على كلام المستخدم، وهو أساس بيعها.
        $this->assertStringContainsString('مقيس من موقعك', $html);
        $this->assertStringContainsString('لا من إجاباتك عن نفسك', $html);
        $this->assertStringNotContainsString('فرضية', $html);
    }

    #[Test]
    public function a_failed_check_carries_its_reason_and_its_repair(): void
    {
        $html = $this->render($this->emptySite());

        $this->assertStringContainsString('يحتاج إصلاحًا', $html);
        $this->assertStringContainsString('الإصلاح:', $html);
        $this->assertStringContainsString('JSON-LD', $html);
    }

    #[Test]
    public function a_passing_check_does_not_shout_a_repair_at_the_reader(): void
    {
        $html = $this->render($this->goodSite());

        // البند السليم يُعرض بحالته فقط؛ إرفاق إصلاح به يجعل القائمة ضجيجًا.
        $this->assertStringContainsString('سليم', $html);
        $this->assertStringContainsString('بيانات المنظمة المنظَّمة', $html);
    }

    #[Test]
    public function an_unreachable_site_says_so_instead_of_showing_a_zero(): void
    {
        $html = $this->render($this->downSite());

        $this->assertStringContainsString('لم نتمكّن من الوصول', $html);
        $this->assertStringContainsString('ليست نتيجة سلبية', $html);

        // لا درجة معروضة: صفر هنا يُقرأ كحكم على موقع لم يُفحص أصلًا.
        $this->assertStringNotContainsString('مقيس من موقعك', $html);
    }

    #[Test]
    public function a_missing_crawl_log_is_named_not_silently_skipped(): void
    {
        $html = $this->render($this->goodSite(), null);

        $this->assertStringContainsString('لم يُرفع سجل خادم بعد', $html);
        $this->assertStringContainsString('ارفع سجل الوصول', $html);
    }

    #[Test]
    public function an_unreadable_log_blames_the_file_not_the_site(): void
    {
        $crawl = app(CrawlLogAnalyzer::class)->analyze("ضجيج\nنص بلا معنى");
        $html = $this->render($this->goodSite(), $crawl);

        $this->assertStringContainsString('تعذّرت قراءة السجل', $html);

        // «صفر زيارة» من ملف لم يُقرأ يصف الملف لا الموقع.
        $this->assertStringNotContainsString('لم يزر موقعك أي بوت', $html);
    }

    #[Test]
    public function zero_visits_from_a_readable_log_is_a_real_finding(): void
    {
        $log = '1.2.3.4 - - ['.now()->format('d/M/Y:H:i:s O').'] "GET / HTTP/1.1" 200 12 "-" "Mozilla/5.0 Chrome/120"';
        $crawl = app(CrawlLogAnalyzer::class)->analyze($log, now()->subDays(30));
        $html = $this->render($this->goodSite(), $crawl);

        // سجل مقروء بلا بوت: نتيجة حقيقية تستحق أن تُقال بوضوح.
        $this->assertStringContainsString('لم يزر موقعك أي بوت', $html);
        $this->assertStringContainsString('لا يظهر في الإجابات', $html);
    }

    #[Test]
    public function pages_the_bot_could_not_read_are_surfaced(): void
    {
        $stamp = now()->format('d/M/Y:H:i:s O');
        $log = '66.1.1.1 - - ['.$stamp.'] "GET / HTTP/1.1" 200 12 "-" "GPTBot/1.1"'
            ."\n".'66.1.1.1 - - ['.$stamp.'] "GET /catalog HTTP/1.1" 403 12 "-" "GPTBot/1.1"';

        $crawl = app(CrawlLogAnalyzer::class)->analyze($log, now()->subDays(30));
        $html = $this->render($this->goodSite(), $crawl);

        $this->assertStringContainsString('صفحات جاءها البوت ولم يقرأها', $html);
        $this->assertStringContainsString('/catalog', $html);
        $this->assertStringContainsString('403', $html);
    }

    #[Test]
    public function the_generated_file_is_stored_and_downloadable(): void
    {
        Storage::fake('local');
        $project = $this->project();

        $path = app(ReadinessCardPdfGenerator::class)->generate($project, $this->emptySite());

        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($path));
    }

    /**
     * @param  array<string, mixed>|null  $crawl
     */
    private function render(SiteAuditResult $audit, ?array $crawl = null): string
    {
        $project = $this->project();

        return view('reports.readiness-card', [
            'project' => $project,
            'audit' => $audit,
            'crawl' => $crawl,
            'score' => app(AxisScorer::class)
                ->score($project, Axis::AiReadiness),
            'fixes' => app(FixList::class)
                ->build($project, [Axis::AiReadiness], $audit),
            'brand' => config('brand'),
            'generatedAt' => now(),
        ])->render();
    }

    private function goodSite(): SiteAuditResult
    {
        return $this->audit([
            '/' => '<html lang="ar" dir="rtl"><h1>متجر</h1><h2>منتجات</h2>'
                .'<script type="application/ld+json">{"@type":"Organization"}</script></html>',
        ]);
    }

    private function emptySite(): SiteAuditResult
    {
        return $this->audit(['/' => '<html><p>مرحبًا</p></html>']);
    }

    private function downSite(): SiteAuditResult
    {
        return $this->audit([]);
    }

    /**
     * @param  array<string, string>  $pages
     */
    private function audit(array $pages): SiteAuditResult
    {
        $fetcher = new class($pages) implements PageFetcher
        {
            /** @param array<string, string> $pages */
            public function __construct(private readonly array $pages) {}

            public function get(string $url): ?string
            {
                $path = parse_url($url, PHP_URL_PATH);

                return $this->pages[$path === null || $path === '' ? '/' : $path] ?? null;
            }
        };

        return (new SiteAudit($fetcher))->audit('https://example.test');
    }

    private function project(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر الجاهزية']);
        $project->brainFacts()->delete();

        return $project->fresh();
    }
}
