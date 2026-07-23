<?php

namespace Tests\Unit\Support\AI;

use App\Support\AI\JsonSchemaValidator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JsonSchemaValidatorTest extends TestCase
{
    private JsonSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new JsonSchemaValidator;
    }

    #[Test]
    public function it_accepts_a_payload_that_matches_the_schema(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['title', 'score'],
            'properties' => [
                'title' => ['type' => 'string', 'minLength' => 3],
                'score' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
            ],
        ];

        $this->assertSame([], $this->validator->validate(['title' => 'عنوان', 'score' => 72], $schema));
    }

    #[Test]
    public function it_reports_missing_required_keys(): void
    {
        $schema = [
            'type' => 'object',
            'required' => ['title', 'score'],
            'properties' => ['title' => ['type' => 'string']],
        ];

        $violations = $this->validator->validate(['title' => 'عنوان'], $schema);

        $this->assertCount(1, $violations);
        $this->assertStringContainsString('score', $violations[0]);
    }

    #[Test]
    public function it_rejects_values_outside_the_allowed_enum(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'severity' => ['type' => 'string', 'enum' => ['critical', 'high', 'medium', 'low']],
            ],
        ];

        $violations = $this->validator->validate(['severity' => 'catastrophic'], $schema);

        $this->assertNotEmpty($violations);
    }

    #[Test]
    public function it_validates_nested_arrays_and_item_counts(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'findings' => [
                    'type' => 'array',
                    'minItems' => 2,
                    'items' => [
                        'type' => 'object',
                        'required' => ['title'],
                        'properties' => ['title' => ['type' => 'string']],
                    ],
                ],
            ],
        ];

        $violations = $this->validator->validate(['findings' => [['title' => 'أ']]], $schema);

        $this->assertNotEmpty($violations);
        $this->assertStringContainsString('العناصر أقل من 2', implode(' ', $violations));
    }

    #[Test]
    public function it_distinguishes_objects_from_lists(): void
    {
        $schema = ['type' => 'object', 'properties' => []];

        $this->assertNotEmpty($this->validator->validate(['a', 'b'], $schema));
    }
}
