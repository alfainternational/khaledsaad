<?php

namespace Tests\Feature\AI\Web;

use App\Domain\AI\Web\Models\WebResearchRun;
use App\Domain\AI\Web\WebClaimVerificationDispatcher;
use App\Domain\AI\Web\WebKnowledgeIngestor;
use App\Domain\AI\Worker\Models\IntelligenceJob;
use App\Domain\AI\Worker\Models\IntelligenceWorker;
use App\Domain\AI\Worker\WorkerProtocolException;
use App\Domain\AI\Worker\WorkerResultApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebClaimVerificationWorkerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dispatcher_queues_one_bounded_local_claim_job_for_an_online_worker(): void
    {
        config()->set('services.private_worker.enabled', true);
        $run = $this->runWithEvidence();
        IntelligenceWorker::query()->create([
            'public_id' => 'worker-web', 'name' => 'Web Worker', 'secret_ciphertext' => 'secret',
            'capabilities_json' => ['local_llm'], 'status' => 'active', 'last_seen_at' => now(),
        ]);

        $dispatcher = app(WebClaimVerificationDispatcher::class);
        $first = $dispatcher->dispatch($run);
        $second = $dispatcher->dispatch($run);

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second));
        $this->assertSame('local_llm', $first->type);
        $this->assertSame('web_claim_verification', $first->payload_json['purpose']);
        $this->assertStringContainsString('اقتباس حرفي', $first->payload_json['prompt']);
        $this->assertLessThanOrEqual(16000, mb_strlen($first->payload_json['prompt']));
        $this->assertDatabaseCount('intelligence_jobs', 1);
    }

    #[Test]
    public function worker_claim_results_are_grounded_then_verified_across_domains(): void
    {
        $run = $this->runWithEvidence();
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'worker-apply', 'name' => 'Apply Worker', 'secret_ciphertext' => 'secret',
            'capabilities_json' => ['local_llm'], 'status' => 'active', 'last_seen_at' => now(),
        ]);
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => 'local_llm', 'status' => 'leased',
            'payload_json' => ['purpose' => 'web_claim_verification', 'run_public_id' => $run->public_id],
            'attempts' => 1, 'max_attempts' => 1,
        ]);
        $result = ['claims' => [[
            'key' => 'annual_growth',
            'evidence' => [
                ['url' => 'https://one.test/report', 'value' => '12%', 'quote' => 'بلغ النمو السنوي 12 بالمئة'],
                ['url' => 'https://two.test/report', 'value' => '12%', 'quote' => 'أكد التقرير أن النمو السنوي 12 بالمئة'],
            ],
        ]]];

        app(WorkerResultApplier::class)->apply($job, $worker, $result);

        $this->assertSame(2, $run->fresh()->verified_count);
        $this->assertSame(0, $run->fresh()->conflict_count);
        $this->assertSame(['verified', 'verified'], $run->results()->orderBy('id')->pluck('verification_status')->all());
    }

    #[Test]
    public function worker_claim_results_reject_quotes_not_present_in_the_source(): void
    {
        $run = $this->runWithEvidence();
        $worker = IntelligenceWorker::query()->create([
            'public_id' => 'worker-bad', 'name' => 'Bad Worker', 'secret_ciphertext' => 'secret',
            'capabilities_json' => ['local_llm'], 'status' => 'active', 'last_seen_at' => now(),
        ]);
        $job = IntelligenceJob::query()->create([
            'public_id' => (string) Str::uuid(), 'type' => 'local_llm', 'status' => 'leased',
            'payload_json' => ['purpose' => 'web_claim_verification', 'run_public_id' => $run->public_id],
            'attempts' => 1, 'max_attempts' => 1,
        ]);

        $this->expectException(WorkerProtocolException::class);
        $this->expectExceptionMessage('quote');
        app(WorkerResultApplier::class)->apply($job, $worker, ['claims' => [[
            'key' => 'invented',
            'evidence' => [[
                'url' => 'https://one.test/report', 'value' => '99%', 'quote' => 'هذا النص غير موجود في المصدر',
            ]],
        ]]]);
    }

    private function runWithEvidence(): WebResearchRun
    {
        $run = WebResearchRun::query()->create([
            'public_id' => (string) Str::uuid(), 'query' => 'نمو السوق',
            'query_hash' => hash('sha256', 'نمو السوق'), 'status' => 'completed',
            'requested_depth' => 2, 'result_count' => 2,
        ]);
        $ingestor = app(WebKnowledgeIngestor::class);
        foreach ([
            ['one', 'بلغ النمو السنوي 12 بالمئة وفق البيانات المنشورة.'],
            ['two', 'أكد التقرير أن النمو السنوي 12 بالمئة خلال الفترة.'],
        ] as $rank => [$host, $text]) {
            $url = "https://{$host}.test/report";
            $ingestor->ingest(
                $run,
                ['provider' => 'test', 'rank' => $rank + 1, 'title' => 'Report', 'url' => $url, 'snippet' => ''],
                ['url' => $url, 'status' => 200],
                ['title' => 'Report', 'canonical_url' => $url, 'language' => 'ar', 'published_at' => now()->subDay()->toIso8601String(), 'text' => $text, 'content_hash' => hash('sha256', $text)],
                ['trust_tier' => 'unknown', 'trust_score' => 70, 'freshness_status' => 'fresh', 'valid_until' => now()->addDays(6)->toIso8601String()],
            );
        }

        return $run->fresh('results');
    }
}
