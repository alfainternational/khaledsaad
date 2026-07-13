<?php

namespace Tests\Feature\AI\Web;

use App\Domain\AI\Web\WebKnowledgeRefresher;
use App\Domain\AI\Web\WebResearchService;
use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebResearchCommandsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function research_command_is_inert_while_verified_research_is_disabled(): void
    {
        config()->set('services.web_search.verified_research', false);

        $this->artisan('knowledge:research-web', ['query' => 'السوق'])
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        $this->assertDatabaseCount('web_research_runs', 0);
    }

    #[Test]
    public function research_command_bounds_depth_and_reports_the_durable_run(): void
    {
        config()->set('services.web_search.verified_research', true);
        $service = Mockery::mock(WebResearchService::class);
        $service->shouldReceive('research')->once()->with('السوق', 8)->andReturn([
            'research_run_id' => 'run-1', 'findings' => [['url' => 'https://example.com']],
        ]);
        $this->app->instance(WebResearchService::class, $service);

        $this->artisan('knowledge:research-web', ['query' => 'السوق', '--depth' => 999])
            ->expectsOutputToContain('run-1')
            ->assertSuccessful();
    }

    #[Test]
    public function refresh_command_uses_a_bounded_batch_and_is_inert_when_disabled(): void
    {
        config()->set('services.web_search.scheduled_refresh', false);
        $this->artisan('knowledge:refresh-web', ['--limit' => 999])
            ->expectsOutputToContain('disabled')
            ->assertSuccessful();

        config()->set('services.web_search.scheduled_refresh', true);
        $refresher = Mockery::mock(WebKnowledgeRefresher::class);
        $refresher->shouldReceive('refreshDue')->once()->with(50, 45)->andReturn([
            'processed' => 4, 'updated' => 3, 'failed' => 1, 'deferred' => 0,
        ]);
        $this->app->instance(WebKnowledgeRefresher::class, $refresher);

        $this->artisan('knowledge:refresh-web', ['--limit' => 999, '--deadline' => 999])
            ->expectsOutputToContain('processed=4')
            ->assertSuccessful();
    }

    #[Test]
    public function refresher_processes_only_due_sources_once_per_host_and_backs_off_failures(): void
    {
        $repository = app(StructuredKnowledgeRepository::class);
        foreach (['https://one.test/a', 'https://one.test/b', 'https://fail.test/a'] as $url) {
            $document = $repository->storeDocument(
                KnowledgeScope::global(), 'web_page', $url, 'Old', 'old content '.$url,
                [['content' => 'old content '.$url]], 50,
            );
            $document->update(['valid_until' => now()->subMinute()]);
        }
        \Illuminate\Support\Facades\Http::fake([
            'https://one.test/a' => \Illuminate\Support\Facades\Http::response(
                '<html lang="ar"><head><title>New</title></head><body>new evidence from first host</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
            'https://fail.test/a' => \Illuminate\Support\Facades\Http::response('', 503, ['Content-Type' => 'text/html']),
        ]);

        $stats = app(WebKnowledgeRefresher::class)->refreshDue(10, 45);

        $this->assertSame(['processed' => 2, 'updated' => 1, 'failed' => 1, 'deferred' => 1], $stats);
        \Illuminate\Support\Facades\Http::assertSentCount(2);
        $failed = \App\Domain\AI\Knowledge\Models\KnowledgeSource::query()
            ->where('canonical_uri', 'https://fail.test/a')->sole();
        $this->assertNotEmpty($failed->meta_json['refresh_next_attempt_at']);
    }
}
