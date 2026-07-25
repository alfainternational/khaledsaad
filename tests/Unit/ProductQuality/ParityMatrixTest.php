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
}
