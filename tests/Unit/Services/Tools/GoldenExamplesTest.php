<?php

namespace Tests\Unit\Services\Tools;

use App\Services\Tools\GoldenExamples;
use App\Services\Tools\PipelineSchemas;
use App\Support\AI\JsonSchemaValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * المثال الذهبي يعلّم النموذج بالقدوة — فإن خالف هو نفسه المخطط أو قواعد
 * الفرضية علّم النموذجَ الخطأ. هذه البوابة تمنع ذلك آليًا.
 */
class GoldenExamplesTest extends TestCase
{
    #[Test]
    public function every_tool_definition_has_its_own_golden_example(): void
    {
        $tools = array_map(
            fn (string $path) => basename($path, '.php'),
            glob(dirname(__DIR__, 4).'/database/data/tools/*.php') ?: [],
        );

        $this->assertNotEmpty($tools);
        $this->assertEqualsCanonicalizing($tools, array_keys(GoldenExamples::catalog()));
    }

    #[Test]
    public function every_example_output_matches_the_synthesis_schema(): void
    {
        $validator = new JsonSchemaValidator;
        $schema = PipelineSchemas::synthesis();

        foreach (GoldenExamples::catalog() as $key => $example) {
            $violations = $validator->validate($example['output'], $schema);
            $this->assertSame([], $violations, "مثال {$key} يخالف المخطط: ".implode(' | ', $violations));
        }
    }

    #[Test]
    public function assumption_findings_obey_the_confidence_cap_and_declare_their_basis(): void
    {
        foreach (GoldenExamples::catalog() as $key => $example) {
            $hasAssumption = false;

            foreach ($example['output']['findings'] as $finding) {
                if ($finding['is_assumption']) {
                    $hasAssumption = true;
                    // نفس سقف ReportComposer: فرضية بثقة عالية تناقض نفسها.
                    $this->assertLessThanOrEqual(75, $finding['confidence'], "فرضية في {$key} تتجاوز سقف الثقة.");
                    $this->assertArrayNotHasKey('evidence', $finding, "فرضية في {$key} تحمل دليلًا.");
                } else {
                    $this->assertArrayHasKey('evidence', $finding, "نتيجة مرصودة في {$key} بلا دليل.");
                }
            }

            // المثال يعلّم التمييز بين المرصود والمستنتج — فيلزمه النوعان.
            $this->assertTrue($hasAssumption, "مثال {$key} بلا فرضية واحدة.");
            $this->assertNotEmpty($example['output']['assumptions'], "فرضية {$key} بلا أساس معلن في assumptions.");
        }
    }

    #[Test]
    public function the_preamble_embeds_the_tool_example_and_falls_back_to_the_shared_one(): void
    {
        $this->assertStringContainsString('عطور نيش', PipelineSchemas::systemPreamble('funnel-audit'));
        $this->assertStringContainsString('كيك منزلي', PipelineSchemas::systemPreamble());
        $this->assertStringContainsString('كيك منزلي', PipelineSchemas::systemPreamble('unknown-tool'));
    }
}
