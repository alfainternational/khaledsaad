<?php

namespace Tests\Unit;

use App\Support\AI\StudioMarkdownSections;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudioMarkdownSectionsTest extends TestCase
{
    #[Test]
    public function splits_on_double_hash_headings(): void
    {
        $text = "مقدمة قصيرة\n\n## القسم أ\nنص أ\n\n## القسم ب\nنص ب";
        $sections = StudioMarkdownSections::split($text);

        $this->assertCount(3, $sections);
        $this->assertSame('', $sections[0]['title']);
        $this->assertStringContainsString('مقدمة', $sections[0]['body']);
        $this->assertSame('القسم أ', $sections[1]['title']);
        $this->assertStringContainsString('نص أ', $sections[1]['body']);
        $this->assertSame('القسم ب', $sections[2]['title']);
    }
}
