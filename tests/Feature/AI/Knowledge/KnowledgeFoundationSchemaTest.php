<?php

namespace Tests\Feature\AI\Knowledge;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeFoundationSchemaTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function it_creates_the_knowledge_foundation_schema(): void
    {
        $tables = [
            'knowledge_sources' => ['account_id', 'workspace_id', 'project_id', 'scope_key', 'kind', 'canonical_uri', 'trust_score'],
            'knowledge_documents' => ['knowledge_source_id', 'content_hash', 'status', 'version', 'valid_until'],
            'knowledge_chunks' => ['knowledge_document_id', 'position', 'content', 'token_count'],
            'knowledge_claims' => ['workspace_id', 'project_id', 'scope_key', 'statement', 'confidence', 'review_status'],
            'knowledge_evidence' => ['knowledge_claim_id', 'knowledge_chunk_id', 'relation', 'quote'],
            'knowledge_reviews' => ['knowledge_claim_id', 'reviewer_user_id', 'decision', 'reason'],
            'intelligence_jobs' => ['workspace_id', 'project_id', 'type', 'status', 'payload_json', 'attempts'],
            'knowledge_embeddings' => ['knowledge_chunk_id', 'model_name', 'model_version', 'dimensions', 'content_hash', 'vector_json', 'status'],
            'knowledge_query_embeddings' => ['scope_key', 'query_hash', 'model_name', 'model_version', 'dimensions', 'vector_json', 'expires_at'],
            'intelligence_evaluation_cases' => ['public_id', 'scope_key', 'account_id', 'workspace_id', 'project_id', 'visibility', 'query', 'expected_chunk_id', 'minimum_rank', 'status'],
            'intelligence_evaluation_runs' => ['public_id', 'engine', 'model_name', 'recall_at_k', 'mean_reciprocal_rank', 'status'],
            'intelligence_evaluation_results' => ['intelligence_evaluation_run_id', 'intelligence_evaluation_case_id', 'rank', 'reciprocal_rank', 'latency_ms', 'passed'],
        ];

        foreach ($tables as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Failed asserting that table [{$table}] exists.");
            $this->assertTrue(Schema::hasColumns($table, $columns), "Failed asserting that table [{$table}] has the required columns.");
        }
    }
}
