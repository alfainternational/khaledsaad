<?php

namespace Tests\Unit\AI\Knowledge;

use App\Domain\AI\Knowledge\Uploads\KnowledgeExtractionException;
use App\Domain\AI\Knowledge\Uploads\TextKnowledgeExtractor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TextKnowledgeExtractorTest extends TestCase
{
    #[Test]
    public function it_extracts_bounded_text_chunks_with_line_locators(): void
    {
        $path = $this->temporary("السطر الأول عن الاحتفاظ\nالسطر الثاني عن النمو\n");

        $result = app(TextKnowledgeExtractor::class)->extract($path, 'text/plain', 'notes.txt');

        $this->assertStringContainsString('السطر الأول', $result->content);
        $this->assertSame(1, $result->chunks[0]['locator']['line_start']);
        $this->assertSame(2, $result->chunks[0]['locator']['line_end']);
        $this->assertSame('ar', $result->language);
    }

    #[Test]
    public function it_flattens_json_deterministically_and_preserves_json_paths(): void
    {
        $path = $this->temporary('{"goal":"زيادة التحويل","metrics":{"retention":72}}');

        $result = app(TextKnowledgeExtractor::class)->extract($path, 'application/json', 'brief.json');

        $this->assertStringContainsString('goal: زيادة التحويل', $result->content);
        $this->assertStringContainsString('metrics.retention: 72', $result->content);
        $this->assertSame('json', $result->metadata['format']);
    }

    #[Test]
    public function it_removes_executable_html_and_rejects_binary_content(): void
    {
        $html = $this->temporary('<h1>خطة النمو</h1><script>alert("secret")</script><p>دليل موثوق</p>');
        $result = app(TextKnowledgeExtractor::class)->extract($html, 'text/html', 'plan.html');

        $this->assertStringContainsString('خطة النمو', $result->content);
        $this->assertStringNotContainsString('secret', $result->content);

        $binary = $this->temporary("valid\0binary");

        try {
            app(TextKnowledgeExtractor::class)->extract($binary, 'text/plain', 'fake.txt');
            $this->fail('Binary content should be rejected.');
        } catch (KnowledgeExtractionException $exception) {
            $this->assertSame('binary_content', $exception->machineCode);
        }
    }

    #[Test]
    public function it_rejects_extracted_text_beyond_the_shared_hosting_bound(): void
    {
        config()->set('services.knowledge.upload_max_text_chars', 20);
        $path = $this->temporary(str_repeat('نص طويل ', 10));

        $this->expectException(KnowledgeExtractionException::class);
        $this->expectExceptionMessage('exceeds the indexing limit');

        app(TextKnowledgeExtractor::class)->extract($path, 'text/plain', 'large.txt');
    }

    private function temporary(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'knowledge-');
        file_put_contents($path, $content);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }
}
