<?php

namespace Tests\Unit\ProductQuality;

use App\Support\ProductQuality\ParityMatrix;
use JsonException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ParityMatrixTest extends TestCase
{
    /**
     * @throws JsonException
     */
    #[Test]
    public function every_capability_has_unique_complete_evidence_fields(): void
    {
        $records = (new ParityMatrix)->records();

        $this->assertNotEmpty($records);
        $this->assertCount(count($records), array_unique(array_column($records, 'id')));

        foreach ($records as $record) {
            $this->assertContains($record['role'], ParityMatrix::ROLES, $record['id']);
            $this->assertContains($record['status'], ParityMatrix::STATUSES, $record['id']);
            $this->assertIsArray($record['web'], $record['id']);
            $this->assertIsArray($record['api'], $record['id']);
            $this->assertIsArray($record['mobile'], $record['id']);
            $this->assertIsArray($record['states'], $record['id']);
            $this->assertIsArray($record['tests'], $record['id']);
            $this->assertNotEmpty($record['states'], $record['id']);
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function verified_capabilities_contain_proof_for_every_applicable_surface(): void
    {
        $missingEvidence = [];

        foreach ((new ParityMatrix)->records() as $record) {
            if ($record['status'] !== 'verified') {
                continue;
            }

            foreach (['web', 'api', 'mobile'] as $surface) {
                if (($record[$surface]['applicable'] ?? true) === false) {
                    continue;
                }

                if (empty($record['tests'][$surface])) {
                    $missingEvidence[] = "{$record['id']}:{$surface}";
                }
            }
        }

        $this->assertSame([], $missingEvidence, 'Verified capabilities must include test evidence.');
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function the_matrix_covers_visitor_customer_and_administrator_roles(): void
    {
        $roles = array_unique(array_column((new ParityMatrix)->records(), 'role'));
        sort($roles);

        $this->assertSame(['admin', 'customer', 'visitor'], $roles);
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function the_matrix_records_verified_layout_evidence_for_every_surface(): void
    {
        $contract = (new ParityMatrix)->layoutContract();

        $this->assertSame([4, 8, 12], $contract['web']['columns']);
        $this->assertSame([600, 1024], $contract['mobile']['breakpoints']);
        $this->assertContains('no_page_horizontal_scroll_320', $contract['constraints']);
        $this->assertContains('colors_unchanged', $contract['constraints']);
        $this->assertContains('shadows_unchanged', $contract['constraints']);

        foreach (['web', 'mobile', 'print'] as $surface) {
            $this->assertSame('verified', $contract[$surface]['status']);
            $this->assertNotEmpty($contract[$surface]['implementation']);
            $this->assertNotEmpty($contract[$surface]['tests']);

            foreach ($contract[$surface]['implementation'] as $path) {
                $this->assertFileExists(dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
            }
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function every_declared_test_evidence_reference_exists(): void
    {
        $matrix = new ParityMatrix;
        $references = [];

        foreach ($matrix->records() as $record) {
            foreach (['web', 'api', 'mobile'] as $surface) {
                array_push($references, ...($record['tests'][$surface] ?? []));
            }
        }

        foreach (['web', 'mobile', 'print'] as $surface) {
            array_push($references, ...($matrix->layoutContract()[$surface]['tests'] ?? []));
        }

        foreach (array_unique($references) as $reference) {
            $relativePath = str_starts_with($reference, 'Tests\\')
                ? preg_replace('/^Tests\\\\/', 'tests\\\\', $reference).'.php'
                : $reference;

            $this->assertFileExists(
                dirname(__DIR__, 3).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath),
                "Missing parity evidence: {$reference}",
            );
        }
    }

    /**
     * @throws JsonException
     */
    #[Test]
    public function every_declared_mobile_screen_exists_in_flutter(): void
    {
        $dartSource = '';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'mobile'.DIRECTORY_SEPARATOR.'lib'),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'dart') {
                $dartSource .= file_get_contents($file->getPathname()) ?: '';
            }
        }

        $screens = [];
        foreach ((new ParityMatrix)->forSurface('mobile') as $record) {
            array_push($screens, ...array_map('trim', explode('+', $record['mobile']['screen'])));
        }

        foreach (array_unique($screens) as $screen) {
            $this->assertMatchesRegularExpression(
                '/class\\s+'.preg_quote($screen, '/').'\\b/',
                $dartSource,
                "Missing Flutter screen declared by parity matrix: {$screen}",
            );
        }
    }
}
