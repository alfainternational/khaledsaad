<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\AiReadiness\ReadinessCollector;
use App\Modules\Diagnosis\Axis;
use App\Modules\Diagnosis\AxisScorer;
use App\Modules\Shared\Evidence\EvidenceLevel;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * المحور الثامن يُقاس فعلًا لا نظريًّا.
 *
 * العطل الذي يحرسه هذا الملف كان حقيقيًّا وصامتًا: `OwnedAssetsCollector`
 * كان مبنيًّا ومختبَرًا منطقيًّا وبصفر مستدعين — أي أن المحور الثامن لم يكن
 * يُقاس إطلاقًا. وكان الغياب يُقرأ خطأً على أنه القرار المعلن بتأجيل
 * `owned_ratio`، بينما السبب أن الجامع لم يُوصَل بشيء.
 *
 * الدرس: القدرة التي لا يستدعيها كودُ إنتاج غير موجودة، مهما كانت خضراء في
 * اختبارها الخاص.
 */
class OwnedAssetsCollectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function auditing_the_site_also_measures_the_owned_assets_axis(): void
    {
        $project = $this->project();
        $this->fetcherReturning('<form><input type="email" name="newsletter"></form>');

        app(ReadinessCollector::class)->collectSiteAudit($project, 'https://example.test');

        $fact = $project->brainFacts()->where('key', 'first_party_capture')->first();

        $this->assertNotNull($fact, 'المحور الثامن لم يُغذَّ رغم اكتمال التدقيق.');
        $this->assertTrue($fact->value_json['value']);
        $this->assertSame('OwnedAssets', $fact->source_module);

        // مصدره صفحة حقيقية لا وصف صاحبه، ولذلك يبلغ measured — وعليه يقوم
        // تسعير المحور (§٥).
        $this->assertSame(EvidenceLevel::Measured, $fact->evidence_level);
    }

    #[Test]
    public function a_site_without_a_capture_form_is_a_finding_not_a_gap(): void
    {
        $project = $this->project();
        $this->fetcherReturning('<html><body><h1>متجرنا</h1></body></html>');

        app(ReadinessCollector::class)->collectSiteAudit($project, 'https://example.test');

        $fact = $project->brainFacts()->where('key', 'first_party_capture')->first();

        // «فُحص فلم يوجد» نتيجة، لا فجوة تغطية: الحقيقة تُكتب بقيمة false.
        $this->assertNotNull($fact);
        $this->assertFalse($fact->value_json['value']);
    }

    #[Test]
    public function an_unreachable_site_writes_nothing_for_the_axis(): void
    {
        $project = $this->project();
        $this->fetcherReturning(null);

        app(ReadinessCollector::class)->collectSiteAudit($project, 'https://example.test');

        // تعذّر الفحص ليس نتيجة فحص: التغطية هي ما يعكس ذلك لا الدرجة (§٤.٣).
        $this->assertNull($project->brainFacts()->where('key', 'first_party_capture')->first());
        $this->assertSame(0.0, app(AxisScorer::class)->score($project, Axis::OwnedAssets)->coverage);
    }

    private function fetcherReturning(?string $html): void
    {
        $this->app->bind(PageFetcher::class, fn () => new class($html) implements PageFetcher
        {
            public function __construct(private readonly ?string $html) {}

            public function get(string $url): ?string
            {
                // robots.txt وllms.txt لا يعنيان هذا الاختبار.
                return str_contains($url, '.txt') ? null : $this->html;
            }
        });
    }

    private function project(): Project
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'نشاطي',
            'website' => 'https://example.test',
        ]);

        $project->brainFacts()->delete();

        return $project->fresh();
    }
}
