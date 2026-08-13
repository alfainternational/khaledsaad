<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedReportPresentationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function all_primary_report_surfaces_use_the_shared_contract_components(): void
    {
        foreach ([
            resource_path('views/app/reports/show.blade.php'),
            resource_path('views/reports/pdf.blade.php'),
            resource_path('views/reports/shared.blade.php'),
        ] as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringContainsString('recommendation-contract', $source, $path);
        }

        $web = (string) file_get_contents(resource_path('views/app/reports/show.blade.php'));
        $pdf = (string) file_get_contents(resource_path('views/reports/pdf.blade.php'));
        $this->assertStringContainsString('score-equation', $web);
        $this->assertStringContainsString('score-equation', $pdf);
        $this->assertStringContainsString('provenance-badge', $web);
        $this->assertStringContainsString('provenance-badge', $pdf);
    }

    #[Test]
    public function banned_anti_machine_copy_is_absent_from_customer_report_sources(): void
    {
        $paths = [
            resource_path('views/app/reports/show.blade.php'),
            resource_path('views/reports/pdf.blade.php'),
            resource_path('views/reports/shared.blade.php'),
            resource_path('views/components/recommendation-contract.blade.php'),
        ];

        foreach ($paths as $path) {
            $source = (string) file_get_contents($path);
            $this->assertStringNotContainsString('ليست نتيجة آلة', $source);
            $this->assertStringNotContainsString('اطلب تطوير المهمة', $source);
        }
    }

    #[Test]
    public function report_print_contract_keeps_action_cards_together_and_hides_screen_controls(): void
    {
        $css = (string) file_get_contents(resource_path('css/workspace.css'));
        $this->assertStringContainsString('break-inside:avoid', $css);
        $this->assertStringContainsString('[data-copy-template]{display:none!important}', $css);
    }
}
