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
            ->expectsOutputToContain('Neutral Arabic')
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
}
