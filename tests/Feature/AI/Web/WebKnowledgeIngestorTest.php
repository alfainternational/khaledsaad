<?php

namespace Tests\Feature\AI\Web;

use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\Models\KnowledgeSource;
use App\Domain\AI\Web\Models\WebResearchRun;
use App\Domain\AI\Web\WebKnowledgeIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebKnowledgeIngestorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_versions_global_web_evidence_idempotently_with_citation_provenance(): void
    {
        $run = WebResearchRun::query()->create([
            'public_id' => (string) Str::uuid(),
            'query' => 'نمو السوق',
            'query_hash' => hash('sha256', 'نمو السوق'),
            'status' => 'running',
            'requested_depth' => 2,
        ]);
        $ingestor = app(WebKnowledgeIngestor::class);
        $page = $this->page('بلغ نمو السوق 12 بالمئة في العام الحالي.');

        $first = $ingestor->ingest($run, $this->searchResult(), $this->fetch(), $page, $this->policy());
        $same = $ingestor->ingest($run, $this->searchResult(), $this->fetch(), $page, $this->policy());

        $this->assertSame($first->knowledge_document_id, $same->knowledge_document_id);
        $this->assertDatabaseCount('knowledge_sources', 1);
        $this->assertDatabaseCount('knowledge_documents', 1);
        $source = KnowledgeSource::query()->sole();
        $document = KnowledgeDocument::query()->sole();
        $this->assertSame('global', $source->visibility);
        $this->assertSame('web_page', $source->kind);
        $this->assertSame('https://example.com/report', $source->canonical_uri);
        $this->assertSame('https://example.com/report', $document->meta_json['citation']['url']);
        $this->assertSame('تقرير السوق', $document->meta_json['citation']['title']);
        $this->assertNotEmpty($document->meta_json['citation']['fetched_at']);
        $this->assertSame('unverified', $first->verification_status);

        $changed = $ingestor->ingest(
            $run,
            $this->searchResult(),
            $this->fetch(),
            $this->page('تحديث: بلغ نمو السوق 13 بالمئة في العام الحالي.'),
            $this->policy(),
        );

        $this->assertNotSame($first->knowledge_document_id, $changed->knowledge_document_id);
        $this->assertDatabaseCount('knowledge_documents', 2);
        $this->assertSame('superseded', $document->fresh()->status);
        $this->assertSame(2, $changed->knowledgeDocument->version);
    }

    private function searchResult(): array
    {
        return [
            'provider' => 'test', 'rank' => 1, 'title' => 'تقرير السوق',
            'url' => 'https://example.com/report?utm_source=test', 'snippet' => 'ملخص التقرير',
        ];
    }

    private function fetch(): array
    {
        return ['url' => 'https://example.com/report', 'status' => 200];
    }

    private function page(string $text): array
    {
        return [
            'title' => 'تقرير السوق', 'canonical_url' => 'https://example.com/report',
            'language' => 'ar', 'published_at' => now()->subDay()->toIso8601String(),
            'text' => $text, 'content_hash' => hash('sha256', $text),
        ];
    }

    private function policy(): array
    {
        return [
            'trust_tier' => 'unknown', 'trust_score' => 50, 'freshness_status' => 'fresh',
            'valid_until' => now()->addDays(6)->toIso8601String(),
        ];
    }
}
