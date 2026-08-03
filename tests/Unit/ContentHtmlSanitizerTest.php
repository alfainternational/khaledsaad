<?php

namespace Tests\Unit;

use App\Services\Content\ContentHtmlSanitizer;
use PHPUnit\Framework\TestCase;

class ContentHtmlSanitizerTest extends TestCase
{
    public function test_it_preserves_editor_markup_and_removes_executable_content(): void
    {
        $html = <<<'HTML'
<h2 onclick="alert(1)">????? ???</h2>
<p style="color:red">?? <strong>???</strong></p>
<script>alert(1)</script>
<a href="javascript:alert(1)" target="_blank">????</a>
<img src="/storage/content/image.jpg" onerror="alert(1)" alt="????">
<iframe src="https://www.youtube-nocookie.com/embed/abc" allowfullscreen></iframe>
<iframe src="https://evil.example/embed/abc"></iframe>
HTML;

        $clean = (new ContentHtmlSanitizer)->sanitize($html);

        $this->assertStringContainsString('<h2>????? ???</h2>', $clean);
        $this->assertStringContainsString('<strong>???</strong>', $clean);
        $this->assertStringContainsString('/storage/content/image.jpg', $clean);
        $this->assertStringContainsString('youtube-nocookie.com/embed/abc', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('evil.example', $clean);
        $this->assertStringNotContainsString('style=', $clean);
    }
}
