<?php

namespace Tests\Unit;

use App\Support\AI\StudioContentRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudioContentRendererTest extends TestCase
{
    #[Test]
    public function it_renders_lists_and_subheadings_into_structured_html(): void
    {
        $html = StudioContentRenderer::render(<<<'TEXT'
        ### النسخة الأولى
        فقرة افتتاحية قصيرة.

        - نقطة أولى
        - نقطة ثانية
        TEXT);

        $this->assertStringContainsString('studio-rich-subheading', $html);
        $this->assertStringContainsString('<ul class="studio-rich-list">', $html);
        $this->assertStringContainsString('<li>نقطة أولى</li>', $html);
    }

    #[Test]
    public function it_renders_markdown_tables_into_html_tables(): void
    {
        $html = StudioContentRenderer::render(<<<'TEXT'
        | الحقل | القيمة |
        | --- | --- |
        | CTA | احجز الآن |
        | الجمهور | أصحاب المشاريع |
        TEXT);

        $this->assertStringContainsString('studio-rich-table', $html);
        $this->assertStringContainsString('<th>الحقل</th>', $html);
        $this->assertStringContainsString('<td>احجز الآن</td>', $html);
    }
}
