<?php

namespace Tests\Feature\AI\Web;

use App\Contracts\WebSearchGateway;
use App\Domain\AI\Web\Models\WebResearchRun;
use App\Domain\AI\Web\WebResearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebResearchServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function verified_research_returns_and_persists_citations_without_claiming_unproven_facts(): void
    {
        config()->set('services.web_search.verified_research', true);
        config()->set('services.web_search.max_fetches_per_run', 2);
        $this->app->instance(WebSearchGateway::class, new class implements WebSearchGateway
        {
            public function search(string $query, int $limit = 5): array
            {
                return [
                    ['provider' => 'one', 'title' => 'Source One', 'url' => 'https://one.test/report', 'snippet' => 'one'],
                    ['provider' => 'two', 'title' => 'Source Two', 'url' => 'https://two.test/report', 'snippet' => 'two'],
                ];
            }
        });
        Http::fake([
            'https://one.test/report' => Http::response($this->html('Source One', 'دليل المصدر الأول'), 200, ['Content-Type' => 'text/html']),
            'https://two.test/report' => Http::response($this->html('Source Two', 'دليل المصدر الثاني'), 200, ['Content-Type' => 'text/html']),
        ]);

        $result = app(WebResearchService::class)->research('نمو السوق', 2);

        $this->assertCount(2, $result['findings']);
        $this->assertSame(['unverified', 'unverified'], array_column($result['findings'], 'verification_status'));
        $this->assertSame(['https://one.test/report', 'https://two.test/report'], array_column($result['findings'], 'url'));
        $this->assertNotEmpty($result['findings'][0]['citation']['fetched_at']);
        $this->assertSame('completed', WebResearchRun::query()->sole()->status);
        $this->assertDatabaseCount('knowledge_sources', 2);
        $this->assertDatabaseCount('knowledge_documents', 2);
    }

    private function html(string $title, string $text): string
    {
        return '<html lang="ar"><head><title>'.$title.'</title><meta property="article:published_time" content="'.now()->subDay()->toIso8601String().'"></head><body><main>'.$text.'</main></body></html>';
    }
}
