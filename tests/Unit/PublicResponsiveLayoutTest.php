<?php

namespace Tests\Unit;

use Tests\TestCase;

class PublicResponsiveLayoutTest extends TestCase
{
    public function test_mobile_header_has_a_single_row_and_moves_all_secondary_actions_into_the_menu(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $view = file_get_contents(resource_path('views/partials/site-header.blade.php'));
        $mobileQuery = strpos($css, '@media (max-width: 900px)');

        $this->assertNotFalse($mobileQuery);

        $mobileRules = substr($css, $mobileQuery, 2600);

        $this->assertStringContainsString(".desktop-nav,\n    .nav-actions", $mobileRules);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) auto;', $mobileRules);
        $this->assertStringContainsString('min-height: 4.25rem;', $mobileRules);
        $this->assertStringContainsString('min-width: 44px;', $mobileRules);
        $this->assertStringContainsString('mobile-menu__utilities', $view);
        $this->assertStringContainsString("@include('partials.theme-toggle')", $view);
    }

    public function test_small_mobile_layout_uses_safe_gutters_and_fluid_text(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $smallQuery = strpos($css, '@media (max-width: 760px)');

        $this->assertNotFalse($smallQuery);

        $smallRules = substr($css, $smallQuery, 4200);

        $this->assertStringContainsString('padding-inline: clamp(1rem, 4vw, 1.25rem);', $smallRules);
        $this->assertStringContainsString('width: min(8.5rem, 44vw);', $smallRules);
        $this->assertStringContainsString('padding-block: clamp(3.5rem, 12vw, 4.5rem);', $smallRules);
    }
}
