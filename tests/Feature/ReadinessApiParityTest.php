<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\Shared\Metrics\MetricKey;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * تكافؤ عقد الجاهزية بين الويب والتطبيق.
 *
 * السطحان يستدعيان الخدمات نفسها، فيجب أن يعرضا الرقم نفسه بالاسم نفسه.
 * اشتقاق في أحدهما يجعل التطبيق يقول ٤١ والموقع يقول ٣٨ بلا سبب ظاهر — وهو
 * ما تمنعه معايير القبول.
 */
class ReadinessApiParityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_api_returns_the_official_metric_names(): void
    {
        $this->fakeSite($this->goodHtml());
        [$user, $project] = $this->ownedProject();

        Sanctum::actingAs($user);
        $this->postJson(route('api.v1.readiness.audit', $project))->assertOk();

        $response = $this->getJson(route('api.v1.readiness.show', $project))->assertOk();

        // أسماء §١٢ حرفيًّا: أي مرادف يخلق مقياسًا ثانيًا بلا قصد.
        $response->assertJsonPath('data.maturity.'.MetricKey::MATURITY_SCORE, fn ($v) => is_int($v));
        $response->assertJsonStructure([
            'data' => [
                'maturity' => [MetricKey::MATURITY_SCORE, 'axes_active', 'axes_total', 'axes'],
                'readiness' => [MetricKey::AXIS_SCORE, MetricKey::AXIS_COVERAGE, MetricKey::READINESS_SCORE],
                'fixes',
            ],
        ]);
    }

    /**
     * العقد يحمل كل قسم يعرضه الويب، لا الدرجة وحدها.
     *
     * العطل الذي يحرسه هذا الاختبار: `history` و`benchmark` و`conflicts` كانت
     * تُرسَل ولا يعرضها التطبيق، ومرّت سنةً كاملة من التكافؤ لأن الفحص كان
     * يقارن رقمًا واحدًا. تكافؤ العقد أن تصل **الأقسام** لا أن يتطابق عدد.
     */
    #[Test]
    public function the_contract_carries_every_section_the_web_renders(): void
    {
        $this->fakeSite($this->goodHtml());
        [$user, $project] = $this->ownedProject();

        Sanctum::actingAs($user);
        $this->postJson(route('api.v1.readiness.audit', $project))->assertOk();

        $data = $this->getJson(route('api.v1.readiness.show', $project))
            ->assertOk()
            ->json('data');

        foreach (['maturity', 'readiness', 'fixes', 'history', 'benchmark', 'conflicts'] as $section) {
            $this->assertArrayHasKey(
                $section,
                $data,
                "القسم {$section} يعرضه الويب ولا يحمله العقد — سطحان يريان تشخيصين.",
            );
        }

        // العتبة تُحسم في الخادم وحده: محسوبة في مكانين تتباعد (§١٣).
        $this->assertArrayHasKey('plottable', $data['history']);
        $this->assertArrayHasKey('points', $data['history']);

        // غياب المقارنة يصل بسببه لا فارغًا (§٤.٣).
        $this->assertArrayHasKey('available', $data['benchmark']);

        if ($data['benchmark']['available'] === false) {
            $this->assertNotEmpty($data['benchmark']['reason']);
        }
    }

    #[Test]
    public function the_app_and_the_web_report_the_same_score(): void
    {
        $this->fakeSite($this->goodHtml());
        [$user, $project] = $this->ownedProject();

        Sanctum::actingAs($user);
        $this->postJson(route('api.v1.readiness.audit', $project))->assertOk();
        $api = $this->getJson(route('api.v1.readiness.show', $project))->json('data.readiness.axis_score');

        $web = $this->actingAs($user)->get(route('app.readiness.show', $project))->assertOk();

        $this->assertIsInt($api);
        $web->assertSee((string) $api, false);
    }

    #[Test]
    public function a_project_without_a_website_is_refused_with_a_reason(): void
    {
        [$user, $project] = $this->ownedProject(website: null);
        Sanctum::actingAs($user);

        $this->postJson(route('api.v1.readiness.audit', $project))
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains((string) $m, 'رابط موقعك'));
    }

    #[Test]
    public function an_uploaded_log_returns_its_own_parse_quality(): void
    {
        $this->fakeSite('<html></html>');
        [$user, $project] = $this->ownedProject();
        Sanctum::actingAs($user);

        $stamp = now()->subDay()->format('d/M/Y:H:i:s O');
        $log = '66.1.1.1 - - ['.$stamp.'] "GET / HTTP/1.1" 200 12 "-" "GPTBot/1.1"';

        $this->postJson(route('api.v1.readiness.log', $project), [
            'log' => UploadedFile::fake()->createWithContent('access.log', $log),
        ])
            ->assertOk()
            ->assertJsonPath('data.total_visits', 1)
            // جودة المدخل تُعلَن في السطحين: سجل نصف مقروء ينتج تقريرًا نصف صادق.
            ->assertJsonPath('data.parse_ratio', 1.0);
    }

    #[Test]
    public function another_workspace_gets_not_found_from_the_api_too(): void
    {
        $this->fakeSite('<html></html>');
        [, $project] = $this->ownedProject();

        // عزل مساحات العمل لا يعرف فرقًا بين السطحين: التطبيق ليس بابًا خلفيًا.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson(route('api.v1.readiness.show', $project))->assertNotFound();
    }

    private function goodHtml(): string
    {
        return '<html lang="ar" dir="rtl"><h1>متجر</h1><h2>منتجات</h2>'
            .'<script type="application/ld+json">{"@type":"Organization"}</script></html>';
    }

    private function fakeSite(string $html): void
    {
        $this->app->bind(PageFetcher::class, fn () => new class($html) implements PageFetcher
        {
            public function __construct(private readonly string $html) {}

            public function get(string $url): ?string
            {
                return str_contains($url, '.txt') ? null : $this->html;
            }
        });
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function ownedProject(?string $website = 'https://example.test'): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'متجر التكافؤ',
            'website' => $website,
        ]);

        return [$user, $project->fresh()];
    }
}
