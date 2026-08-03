<?php

namespace Tests\Unit;

use DOMDocument;
use Tests\TestCase;

class MarketingCoursePackageTest extends TestCase
{
    public function test_package_contains_twenty_ordered_lessons_with_valid_hashes(): void
    {
        $manifest = require database_path('data/content/marketing-course/manifest.php');

        $this->assertCount(20, $manifest['lessons']);
        $this->assertSame(range(1, 20), array_column($manifest['lessons'], 'order'));

        foreach ($manifest['lessons'] as $lesson) {
            $data = require database_path($lesson['path']);

            $this->assertSame($data['source_text_hash'], hash('sha256', $data['source_text']));
            $this->assertSame($data['source_text'], $this->visibleText($data['body_html']));
            $this->assertSame($lesson['source_key'], $data['source_key']);
            $this->assertNotEmpty($data['learning_meta']['outline']);
        }
    }

    private function visibleText(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        @$document->loadHTML(
            '<?xml encoding="UTF-8"><main>'.$html.'</main>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        $main = $document->getElementsByTagName('main')->item(0);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($main?->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
