<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Services\Tools\PipelineSchemas;
use App\Services\Tools\ToolBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PromptVersioningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_new_prompt_release_preserves_the_historical_tool_version(): void
    {
        $builder = app(ToolBuilder::class);
        $builder->sync($this->definition(1, 'النص التاريخي'));
        $builder->sync($this->definition(2, 'النص المحسن'));

        $tool = Tool::where('key', 'version-test')->firstOrFail();

        $this->assertCount(2, $tool->versions);
        $this->assertSame(2, $tool->currentVersion->version);
        $this->assertSame(
            'النص التاريخي',
            $tool->versions()->where('version', 1)->firstOrFail()->prompts()->value('content'),
        );
        $this->assertSame(
            'النص المحسن',
            $tool->versions()->where('version', 2)->firstOrFail()->prompts()->value('content'),
        );
    }

    /** @return array<string, mixed> */
    private function definition(int $version, string $prompt): array
    {
        return [
            'key' => 'version-test',
            'name' => 'Version Test',
            'title' => 'اختبار الإصدار',
            'description' => 'تعريف مخصص لاختبار حفظ التاريخ.',
            'category' => 'اختبار',
            'status' => 'published',
            'version' => [
                'number' => $version,
                'credit_cost' => 1,
                'output_schema' => PipelineSchemas::synthesis(),
                'scoring_rules' => ['rules' => []],
                'section_plan' => [],
            ],
            'fields' => [],
            'prompts' => ['synthesis' => $prompt],
        ];
    }
}
