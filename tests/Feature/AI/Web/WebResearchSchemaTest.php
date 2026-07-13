<?php

namespace Tests\Feature\AI\Web;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebResearchSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function research_runs_and_results_retain_provenance_freshness_and_verification_state(): void
    {
        $this->assertTrue(Schema::hasColumns('web_research_runs', [
            'public_id', 'query', 'query_hash', 'status', 'requested_depth',
            'result_count', 'verified_count', 'conflict_count', 'started_at',
            'completed_at', 'checkpoint_json', 'error_code',
        ]));

        $this->assertTrue(Schema::hasColumns('web_research_results', [
            'web_research_run_id', 'knowledge_source_id', 'knowledge_document_id',
            'provider', 'rank', 'title', 'original_url', 'normalized_url',
            'domain', 'snippet', 'content_hash', 'fetch_status', 'http_status',
            'trust_tier', 'trust_score', 'freshness_status', 'verification_status',
            'published_at', 'fetched_at', 'valid_until', 'meta_json',
        ]));
    }
}
