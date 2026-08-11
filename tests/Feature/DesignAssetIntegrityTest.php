<?php

namespace Tests\Feature;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class DesignAssetIntegrityTest extends TestCase
{
    #[Test]
    public function every_design_png_is_transparent_and_used_only_once(): void
    {
        $assets = glob(public_path('assets/design/*.png'));
        $this->assertNotEmpty($assets);

        $views = collect(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS),
        ))
            ->filter(fn (SplFileInfo $file): bool => $file->isFile() && str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn (SplFileInfo $file): string => file_get_contents($file->getPathname()))
            ->implode("\n");

        foreach ($assets as $asset) {
            $png = file_get_contents($asset);
            $name = basename($asset);

            $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8), "{$name} must be a PNG.");
            $this->assertContains(ord($png[25]), [4, 6], "{$name} must contain an alpha channel.");
            $directReferences = substr_count($views, "assets/design/{$name}");
            $mappedReferences = substr_count($views, "'file' => '{$name}'");
            $this->assertSame(1, $directReferences + $mappedReferences, "{$name} must be used in exactly one view position.");
        }
    }

    #[Test]
    public function numbered_public_journeys_pair_numbers_with_icons(): void
    {
        $catalog = file_get_contents(resource_path('views/site/tools/index.blade.php'));
        $tool = file_get_contents(resource_path('views/site/tools/show.blade.php'));
        $pages = file_get_contents(resource_path('views/site/pages/show.blade.php'));

        $this->assertSame(4, substr_count($catalog, '<x-section-icon name='));
        $this->assertStringContainsString('<x-section-icon :name=', $tool);
        $this->assertStringContainsString('public-page-card__marker', $pages);
        $this->assertStringContainsString('<x-section-icon :name=', $pages);
    }

    #[Test]
    public function every_shared_public_page_has_its_own_centered_visual_and_compact_split_layout(): void
    {
        $view = file_get_contents(resource_path('views/site/pages/show.blade.php'));
        $css = file_get_contents(resource_path('css/interface-system.css'));

        foreach ([
            'methodology' => 'page-methodology-flow.png',
            'principles' => 'page-principles-trust.png',
            'services' => 'page-services-outcomes.png',
            'knowledge' => 'page-knowledge-actions.png',
            'faq' => 'page-faq-decisions.png',
            'sample-report' => 'page-sample-report.png',
        ] as $page => $asset) {
            $this->assertStringContainsString("'{$page}' =>", $view);
            $this->assertSame(1, substr_count($view, $asset));
        }

        $this->assertStringContainsString("grid-template-areas: 'copy visual'", $css);
        $this->assertStringContainsString('.public-page-hero__visual', $css);
        $this->assertStringContainsString('place-items: center', $css);
        $this->assertStringContainsString('font-size: clamp(2.05rem, 3vw, 2.75rem)', $css);
    }
}
