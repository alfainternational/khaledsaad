<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedInterfaceCoverageTest extends TestCase
{
    #[Test]
    public function every_interface_family_is_registered_and_exposed_by_its_shared_layout(): void
    {
        $this->assertFileExists(config_path('interface.php'));

        $registry = require config_path('interface.php');

        $this->assertSame('v2', $registry['version']);
        $this->assertSame(
            ['public', 'auth', 'workspace', 'admin', 'reports', 'flutter'],
            array_keys($registry['families']),
        );

        foreach (['public', 'app', 'auth'] as $layout) {
            $contents = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString('data-interface-system="v2"', $contents, $layout);
            $this->assertStringContainsString('data-interface-family=', $contents, $layout);
        }

        $this->assertFileExists(resource_path('css/interface-system.css'));
        $this->assertStringContainsString(
            "@import './interface-system.css';",
            file_get_contents(resource_path('css/app.css')),
        );
    }

    #[Test]
    public function all_human_facing_blade_pages_belong_to_an_approved_layout_contract(): void
    {
        $roots = ['app', 'admin', 'site', 'auth', 'errors'];
        $approved = ['layouts.app', 'layouts.public', 'layouts.auth'];
        $standalone = [
            'site/content/llms.blade.php',
            'site/pages/profile-pdf.blade.php',
        ];
        $checked = 0;

        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path("views/{$root}")),
            );

            foreach ($iterator as $view) {
                $path = $view->getPathname();

                if (! str_ends_with($path, '.blade.php')
                    || str_contains($path, DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR)
                    || str_starts_with(basename($path), '_')) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($path, strlen(resource_path('views')) + 1));

                if (in_array($relative, $standalone, true)) {
                    continue;
                }

                $contents = file_get_contents($path);
                $matches = collect($approved)->contains(
                    fn (string $layout): bool => str_contains($contents, "@extends('{$layout}')")
                        || str_contains($contents, "@extends(\"{$layout}\")"),
                );

                $this->assertTrue($matches, $relative);
                $checked++;
            }
        }

        $this->assertGreaterThanOrEqual(85, $checked);
    }

    #[Test]
    public function flutter_uses_ibm_plex_and_contains_no_hacen_interface_reference(): void
    {
        $pubspec = file_get_contents(base_path('mobile/pubspec.yaml'));
        $theme = file_get_contents(base_path('mobile/lib/core/theme/app_theme.dart'));

        $this->assertStringContainsString('family: IBMPlexSansArabic', $pubspec);
        $this->assertStringContainsString("fontFamily: 'IBMPlexSansArabic'", $theme);
        $this->assertStringNotContainsString('HacenTunisia', $pubspec.$theme);

        $dartFiles = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('mobile/lib')),
        );

        foreach ($dartFiles as $file) {
            if (! str_ends_with($file->getPathname(), '.dart')) {
                continue;
            }

            $this->assertStringNotContainsString(
                'HacenTunisia',
                file_get_contents($file->getPathname()),
                $file->getPathname(),
            );
        }
    }

    #[Test]
    public function visible_arabic_interface_copy_uses_latin_digits_only(): void
    {
        $roots = [resource_path('views'), base_path('mobile/lib')];

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                $path = $file->getPathname();

                if (! str_ends_with($path, '.blade.php') && ! str_ends_with($path, '.dart')) {
                    continue;
                }

                $contents = file_get_contents($path);

                if (str_ends_with($path, '.blade.php')) {
                    $contents = preg_replace('/{{--.*?--}}/s', '', $contents) ?? $contents;
                } else {
                    $contents = preg_replace('~/\*.*?\*/|//[^\r\n]*~s', '', $contents) ?? $contents;
                }

                $contents = preg_replace('~/\*.*?\*/|^\s*//[^\r\n]*~ms', '', $contents) ?? $contents;

                $this->assertDoesNotMatchRegularExpression(
                    '/[٠١٢٣٤٥٦٧٨٩]/u',
                    $contents,
                    $path,
                );
            }
        }
    }

    #[Test]
    public function every_report_surface_declares_the_report_interface_family(): void
    {
        $reportViews = [
            'reports/shared.blade.php',
            'reports/pdf.blade.php',
            'reports/readiness-card.blade.php',
            'agency-reports/shared.blade.php',
            'agency-reports/pdf.blade.php',
            'agency-reports/owner-pdf.blade.php',
            'app/reports/show.blade.php',
            'app/agency-reports/index.blade.php',
            'app/agency-reports/show.blade.php',
            'app/agency-reports/brief.blade.php',
        ];

        foreach ($reportViews as $view) {
            $contents = file_get_contents(resource_path("views/{$view}"));

            $this->assertTrue(
                str_contains($contents, "@section('interface_family', 'reports')")
                    || str_contains($contents, 'data-interface-family="reports"'),
                $view,
            );
        }
    }
}
