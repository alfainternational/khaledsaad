<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Modules\Brain\BrainReader;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * رحلة الجاهزية من الشاشة: افحص، ارفع سجلًّا، حمّل البطاقة.
 *
 * الوحدات مختبَرة منفردة؛ هذا يتحقق أن المسار الذي يسلكه المستخدم فعلًا يصل
 * إلى آخره ولا يتعثّر في تفويض أو ربط.
 */
class ReadinessJourneyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_owner_runs_an_audit_and_sees_a_measured_score(): void
    {
        $this->fakeSite([
            '/' => '<html lang="ar" dir="rtl"><h1>متجر</h1><h2>منتجات</h2>'
                .'<script type="application/ld+json">{"@type":"Organization"}</script></html>',
        ]);

        [$user, $project] = $this->ownedProject('https://example.test');

        $this->actingAs($user)
            ->post(route('app.readiness.audit', $project))
            ->assertRedirect(route('app.readiness.show', $project));

        // الحقائق وصلت الدماغ، فالدرجة تُحسب منها لا من الطلب.
        $this->assertNotNull(app(BrainReader::class)->fact($project, 'schema_organization'));

        $this->actingAs($user)
            ->get(route('app.readiness.show', $project))
            ->assertOk()
            ->assertSee('مقيس من موقعك')
            ->assertSee('تغطية');
    }

    #[Test]
    public function a_project_without_a_website_is_told_why_instead_of_failing_silently(): void
    {
        [$user, $project] = $this->ownedProject(null);

        $this->actingAs($user)
            ->from(route('app.readiness.show', $project))
            ->post(route('app.readiness.audit', $project))
            ->assertSessionHasErrors('website');
    }

    #[Test]
    public function an_uploaded_log_is_analysed_and_reported_back(): void
    {
        $this->fakeSite(['/' => '<html></html>']);
        [$user, $project] = $this->ownedProject('https://example.test');

        $stamp = now()->subDay()->format('d/M/Y:H:i:s O');
        $log = '66.1.1.1 - - ['.$stamp.'] "GET / HTTP/1.1" 200 12 "-" "GPTBot/1.1"';

        $this->actingAs($user)
            ->post(route('app.readiness.log', $project), [
                'log' => UploadedFile::fake()->createWithContent('access.log', $log),
            ])
            ->assertRedirect(route('app.readiness.show', $project))
            ->assertSessionHas('readiness.crawl');

        $fact = app(BrainReader::class)->fact($project, 'ai_bot_visits_30d');
        $this->assertSame(1, $fact?->value_json['value']);
    }

    #[Test]
    public function an_unreadable_log_says_so_and_writes_nothing(): void
    {
        $this->fakeSite(['/' => '<html></html>']);
        [$user, $project] = $this->ownedProject('https://example.test');

        $this->actingAs($user)
            ->post(route('app.readiness.log', $project), [
                'log' => UploadedFile::fake()->createWithContent('junk.log', "ضجيج\nنص"),
            ])
            ->assertRedirect();

        // «صفر زيارة» من ملف لم يُقرأ يصف الملف لا الموقع، فلا يُكتب شيء.
        $this->assertNull(app(BrainReader::class)->fact($project, 'ai_bot_visits_30d'));
    }

    #[Test]
    public function the_card_downloads_as_a_pdf(): void
    {
        $this->fakeSite(['/' => '<html lang="ar" dir="rtl"><h1>أ</h1><h2>ب</h2></html>']);
        [$user, $project] = $this->ownedProject('https://example.test');

        $response = $this->actingAs($user)->get(route('app.readiness.download', $project));

        $response->assertOk();
        $this->assertStringContainsString('pdf', strtolower($response->headers->get('content-type') ?? ''));
    }

    #[Test]
    public function another_workspace_cannot_reach_the_card(): void
    {
        $this->fakeSite(['/' => '<html></html>']);
        [, $project] = $this->ownedProject('https://example.test');

        // عزل مساحات العمل يمرّ عبر ProjectOwnership وحدها، فلا مسار وصول ثانٍ.
        $this->actingAs(User::factory()->create())
            ->get(route('app.readiness.show', $project))
            ->assertNotFound();
    }

    /**
     * @param  array<string, string>  $pages
     */
    private function fakeSite(array $pages): void
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
    }

    /**
     * @return array{0: User, 1: Project}
     */
    private function ownedProject(?string $website): array
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'متجر الرحلة',
            'website' => $website,
        ]);

        return [$user, $project->fresh()];
    }
}
