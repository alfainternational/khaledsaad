<?php

namespace Tests\Feature\AI\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeHealthCommandTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function json_health_reports_foundation_counts_and_only_expired_active_documents_as_stale(): void
    {
        $repository = app(StructuredKnowledgeRepository::class);
        $stale = $repository->storeDocument(
            KnowledgeScope::global(),
            'health_test',
            'health://stale',
            'Stale',
            'stale content',
            [['heading' => null, 'content' => 'stale content', 'locator' => []]],
        );
        $fresh = $repository->storeDocument(
            KnowledgeScope::global(),
            'health_test',
            'health://fresh',
            'Fresh',
            'fresh content',
            [['heading' => null, 'content' => 'fresh content', 'locator' => []]],
        );
        $superseded = $repository->storeDocument(
            KnowledgeScope::global(),
            'health_test',
            'health://superseded',
            'Superseded',
            'superseded content',
            [['heading' => null, 'content' => 'superseded content', 'locator' => []]],
        );

        $stale->update(['valid_until' => now()->subMinute()]);
        $fresh->update(['valid_until' => now()->addMinute()]);
        $superseded->update(['status' => 'superseded', 'valid_until' => now()->subMinute()]);
        $stale->source->update(['meta_json' => ['legacy_pending_generation' => str_repeat('a', 64)]]);

        DB::table('knowledge_claims')->insert([
            'scope_key' => KnowledgeScope::global()->key(),
            'statement' => 'Candidate',
            'statement_hash' => hash('sha256', 'Candidate'),
            'claim_type' => 'fact',
            'confidence' => 50,
            'review_status' => 'candidate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('knowledge_claims')->insert([
            'scope_key' => KnowledgeScope::global()->key(),
            'statement' => 'Accepted',
            'statement_hash' => hash('sha256', 'Accepted'),
            'claim_type' => 'fact',
            'confidence' => 90,
            'review_status' => 'accepted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('intelligence_jobs')->insert([
            'public_id' => (string) Str::uuid(),
            'type' => 'health_test',
            'status' => 'failed',
            'payload_json' => '{}',
            'attempts' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('intelligence_jobs')->insert([
            'public_id' => (string) Str::uuid(),
            'type' => 'health_test',
            'status' => 'queued',
            'payload_json' => '{}',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('knowledge:health', ['--json' => true]));

        $this->assertSame([
            'sources' => 3,
            'documents' => 3,
            'chunks' => 3,
            'uploads' => 0,
            'uploads_indexed' => 0,
            'uploads_stored' => 0,
            'uploads_failed' => 0,
            'unlinked_uploads' => 0,
            'candidate_claims' => 1,
            'failed_jobs' => 1,
            'queued_worker_jobs' => 1,
            'leased_worker_jobs' => 0,
            'active_workers' => 0,
            'online_workers' => 0,
            'pending_reconciliations' => 1,
            'stale_documents' => 1,
            'active_embeddings' => 0,
            'embedding_coverage_percent' => 0,
            'stale_embeddings' => 0,
            'pending_embedding_jobs' => 0,
            'latest_evaluation_status' => 'none',
            'latest_evaluation_recall' => null,
            'latest_evaluation_mrr' => null,
        ], json_decode(trim(Artisan::output()), true, 512, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function text_health_prints_one_metric_per_line(): void
    {
        $this->artisan('knowledge:health')
            ->expectsOutput('sources: 0')
            ->expectsOutput('documents: 0')
            ->expectsOutput('chunks: 0')
            ->expectsOutput('uploads: 0')
            ->expectsOutput('uploads_indexed: 0')
            ->expectsOutput('uploads_stored: 0')
            ->expectsOutput('uploads_failed: 0')
            ->expectsOutput('unlinked_uploads: 0')
            ->expectsOutput('candidate_claims: 0')
            ->expectsOutput('failed_jobs: 0')
            ->expectsOutput('queued_worker_jobs: 0')
            ->expectsOutput('leased_worker_jobs: 0')
            ->expectsOutput('active_workers: 0')
            ->expectsOutput('online_workers: 0')
            ->expectsOutput('pending_reconciliations: 0')
            ->expectsOutput('stale_documents: 0')
            ->expectsOutput('active_embeddings: 0')
            ->expectsOutput('embedding_coverage_percent: 0')
            ->expectsOutput('stale_embeddings: 0')
            ->expectsOutput('pending_embedding_jobs: 0')
            ->expectsOutput('latest_evaluation_status: none')
            ->expectsOutput('latest_evaluation_recall: ')
            ->expectsOutput('latest_evaluation_mrr: ')
            ->assertSuccessful();
    }
}
