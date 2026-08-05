<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProductQualityCommandTest extends TestCase
{
    #[Test]
    public function product_audit_reports_matrix_and_neutral_arabic_results(): void
    {
        $this->artisan('product:audit')
            ->expectsOutputToContain('Parity matrix')
            ->expectsOutputToContain('API route coverage')
            ->expectsOutputToContain('Neutral Arabic')
            ->expectsOutputToContain('Prompt v2 evaluation: PASS (88 fixtures; 11 tools)')
            ->assertSuccessful();
    }

    #[Test]
    public function product_audit_fails_for_an_invalid_matrix(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'parity-matrix-');
        $this->assertNotFalse($path);

        try {
            file_put_contents($path, '{"capabilities":[{"id":"duplicate"},{"id":"duplicate"}]}');

            $this->artisan('product:audit', ['--matrix' => $path])
                ->expectsOutputToContain('Parity matrix: FAIL')
                ->assertFailed();
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function format_audit_reports_missing_flutter_sources_without_crashing(): void
    {
        $missingSource = storage_path('framework/testing/missing-flutter-consultation-source');

        $this->assertDirectoryDoesNotExist($missingSource);

        $this->artisan('product:audit', [
            '--require-formats' => true,
            '--mobile-source' => $missingSource,
        ])
            ->expectsOutputToContain('Form answer formats: FAIL')
            ->expectsOutputToContain('Flutter form source is missing')
            ->assertFailed();
    }

    #[Test]
    public function prompt_fixture_audit_skips_an_undeployed_catalog_unless_it_is_required(): void
    {
        $missingCatalog = storage_path('framework/testing/missing-prompt-fixtures.php');

        $this->assertFileDoesNotExist($missingCatalog);

        $this->artisan('product:audit', ['--prompt-fixtures' => $missingCatalog])
            ->expectsOutputToContain('Prompt v2 evaluation: SKIP')
            ->assertSuccessful();

        $this->artisan('product:audit', [
            '--prompt-fixtures' => $missingCatalog,
            '--require-prompt-fixtures' => true,
        ])
            ->expectsOutputToContain('Prompt v2 evaluation: FAIL')
            ->assertFailed();
    }
}
