<?php

namespace Tests\Unit;

use Tests\TestCase;

class LearningMagazineStyleTest extends TestCase
{
    public function test_learning_surfaces_follow_the_active_color_theme(): void
    {
        $css = file_get_contents(resource_path('css/content-library.css'));

        preg_match('/\.learning-section \{(?<rules>.*?)\n\}/s', $css, $section);
        preg_match('/\.learning-outline \{(?<rules>.*?)\n\}/s', $css, $outline);
        preg_match('/\.content-prose \.learning-section h2 \{(?<rules>.*?)\n\}/s', $css, $heading);

        $this->assertStringContainsString('background: var(--surface);', $section['rules'] ?? '');
        $this->assertStringContainsString('color: var(--ink);', $section['rules'] ?? '');
        $this->assertStringContainsString('background: var(--surface);', $outline['rules'] ?? '');
        $this->assertStringContainsString('color: var(--ink);', $heading['rules'] ?? '');
    }
}
