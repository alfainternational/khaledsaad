<?php

namespace Tests\Unit\ProductQuality;

use App\Support\ProductQuality\NeutralArabicScanner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NeutralArabicScannerTest extends TestCase
{
    #[Test]
    public function it_reports_dialect_bound_copy_with_file_and_line(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'neutral-arabic-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, "<h1>الصفحة دي ما موجودة عندنا.</h1>\n");

            $issues = (new NeutralArabicScanner)->scan([$path]);

            $this->assertCount(1, $issues);
            $this->assertSame('دي', $issues[0]['term']);
            $this->assertSame(1, $issues[0]['line']);
            $this->assertSame('هذه', $issues[0]['replacement']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_ignores_comment_only_lines_and_matches_visible_source_lines(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'neutral-arabic-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, "// وين في تعليق تقني\nconst label = 'وين وصل مشروعك؟';\n");

            $issues = (new NeutralArabicScanner)->scan([$path]);

            $this->assertCount(1, $issues);
            $this->assertSame(2, $issues[0]['line']);
            $this->assertSame('وين', $issues[0]['term']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function repository_user_facing_copy_uses_neutral_arabic(): void
    {
        $issues = (new NeutralArabicScanner)->scanDefaultPaths();

        $this->assertSame(
            [],
            $issues,
            json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    #[Test]
    public function default_source_paths_include_public_configuration_copy(): void
    {
        $paths = (new NeutralArabicScanner)->defaultPaths();
        $normalized = array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            $paths,
        );

        $this->assertContains(
            str_replace('\\', '/', dirname(__DIR__, 3).'/config'),
            $normalized,
        );
    }
}
