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

    public function test_late_learning_rules_keep_the_reading_grid_single_column_on_mobile(): void
    {
        $css = file_get_contents(resource_path('css/content-library.css'));
        // نقطة التوقّف من التوكنز الأربع (INV-10): كانت 1050px قبل توحيدها.
        $lastMobileQuery = strrpos($css, '@media (max-width: 1023px)');

        $this->assertNotFalse($lastMobileQuery);

        $mobileRules = substr($css, $lastMobileQuery, 700);

        $this->assertStringContainsString(
            '.content-page--learning .content-reading-grid',
            $mobileRules,
        );
        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, 1fr);',
            $mobileRules,
        );
        $this->assertStringContainsString('overscroll-behavior-inline: contain;', $mobileRules);
        $this->assertStringContainsString('scroll-padding-inline: 1rem;', $mobileRules);
    }
}
