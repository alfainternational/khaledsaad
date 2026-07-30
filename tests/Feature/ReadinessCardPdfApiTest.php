<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Modules\AiReadiness\Contracts\PageFetcher;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * بطاقة الجاهزية PDF عبر api/v1 — كانت على الويب وحده.
 *
 * الفجوة التي يغلقها: مستخدم التطبيق يرى شاشة الجاهزية ولا يملك تنزيل بطاقتها،
 * بينما يملكها مستخدم الويب. نظير مطابق محروس بـ`diagnosis.full` نفسه (§١٥
 * بند ٨).
 */
class ReadinessCardPdfApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_api_streams_the_readiness_card_as_a_pdf(): void
    {
        $this->fakeSite(['/' => '<html lang="ar" dir="rtl"><h1>أ</h1><h2>ب</h2></html>']);
        [$user, $project] = $this->ownedProject('https://example.test');

        Sanctum::actingAs($user);

        $response = $this->get(route('api.v1.readiness.pdf', $project));

        $response->assertOk();
        $this->assertStringContainsString(
            'pdf',
            strtolower($response->headers->get('content-type') ?? ''),
        );
    }

    #[Test]
    public function a_project_without_a_website_is_refused(): void
    {
        [$user, $project] = $this->ownedProject(null);
        Sanctum::actingAs($user);

        $this->get(route('api.v1.readiness.pdf', $project))->assertStatus(422);
    }

    #[Test]
    public function another_workspace_cannot_download_the_card(): void
    {
        $this->fakeSite(['/' => '<html></html>']);
        [, $project] = $this->ownedProject('https://example.test');

        Sanctum::actingAs(User::factory()->create());

        $this->get(route('api.v1.readiness.pdf', $project))->assertNotFound();
    }

    /**
     * @param  array<string, string>  $pages
     */
    private function fakeSite(array $pages): void
    {
        $this->app->bind(PageFetcher::class, fn () => new class($pages) implements PageFetcher
        {
            /** @param  array<string, string>  $pages */
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
