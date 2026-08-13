<?php

namespace Tests\Unit;

use Tests\TestCase;

class MarketingLearningLayoutContractTest extends TestCase
{
    public function test_every_interface_family_uses_one_centimeter_outer_gutter(): void
    {
        $interface = file_get_contents(resource_path('css/interface-system.css'));
        $app = file_get_contents(resource_path('css/app.css'));
        $workspace = file_get_contents(resource_path('css/workspace.css'));
        $reference = file_get_contents(resource_path('css/reference-ui.css'));

        $this->assertStringContainsString('--ui-page-inline: 1cm', $interface);
        $this->assertStringContainsString('padding-inline: var(--ui-page-inline)', $app);
        $this->assertStringContainsString('padding: 1.9rem var(--ui-page-inline) 4.5rem', $workspace);
        $this->assertStringContainsString('padding-inline: var(--ui-page-inline)', $reference);
        $this->assertStringNotContainsString('width: calc(100% - clamp(28px, 4vw, 76px))', $reference);
    }

    public function test_learning_page_uses_a_fluid_reading_grid_instead_of_page_caps(): void
    {
        $css = file_get_contents(resource_path('css/content-library.css'));

        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) minmax(17rem, 21rem)', $css);
        $this->assertStringNotContainsString('grid-template-columns: minmax(0, 48rem) minmax(17rem, 21rem)', $css);
        $this->assertStringNotContainsString('max-width: 1180px', $css);
    }
}
