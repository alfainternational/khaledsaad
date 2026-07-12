<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BrandingAssetsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    public function test_web_layouts_use_the_approved_brand_assets(): void
    {
        foreach (['marketing.blade.php', 'app.blade.php', 'admin.blade.php'] as $layout) {
            $contents = file_get_contents("{$this->root}/resources/views/layouts/{$layout}");
            $this->assertStringContainsString('brand/icon-app.png', $contents);
        }

        $this->assertFileExists("{$this->root}/public/brand/logo-ar.png");
        $this->assertFileExists("{$this->root}/public/brand/icon-app.png");
        $this->assertFileExists("{$this->root}/public/favicon.ico");
    }

    public function test_mobile_brand_widget_uses_the_approved_image_asset(): void
    {
        $widget = file_get_contents("{$this->root}/mobile/lib/features/shared/widgets/brand_mark.dart");
        $pubspec = file_get_contents("{$this->root}/mobile/pubspec.yaml");

        $this->assertStringContainsString('assets/brand/icon-app.png', $widget);
        $this->assertStringContainsString('assets/brand/', $pubspec);
        $this->assertFileExists("{$this->root}/mobile/assets/brand/icon-app.png");
    }
}
