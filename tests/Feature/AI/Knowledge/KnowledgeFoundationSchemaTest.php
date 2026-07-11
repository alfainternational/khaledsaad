<?php

namespace Tests\Feature\AI\Knowledge;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeFoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

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
        ];

        foreach ($tables as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), "Failed asserting that table [{$table}] exists.");
            $this->assertTrue(Schema::hasColumns($table, $columns), "Failed asserting that table [{$table}] has the required columns.");
        }
    }
}
