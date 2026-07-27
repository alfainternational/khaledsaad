<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdaptiveInterfaceLayoutTest extends TestCase
{
    #[Test]
    public function workspace_css_defines_the_approved_semantic_layout_contract(): void
    {
        $css = file_get_contents(resource_path('css/workspace.css'));

        foreach ([
            '--layout-reading-max: 46rem',
            '--layout-form-max: 56rem',
            '--layout-operational-max: 87.5rem',
            '--layout-page-gap: 2rem',
            '--layout-section-gap: 1.5rem',
            '.layout-grid',
            '.layout-span-12',
            '.layout-span-9',
            '.layout-span-8',
            '.layout-span-6',
            '.layout-span-4',
            '.layout-span-3',
            '.layout-metrics',
            '.layout-main-aside',
            '.layout-report',
            '.layout-form-aside',
            '.layout-page--reading',
            '.layout-page--form',
            '.layout-page--operational',
            '@media (max-width: 64rem)',
            '@media (max-width: 48rem)',
        ] as $contract) {
            $this->assertStringContainsString($contract, $css, $contract);
        }
    }
}
