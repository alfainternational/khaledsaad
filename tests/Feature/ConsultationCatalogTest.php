<?php

namespace Tests\Feature;

use App\Models\ConsultationBlueprint;
use App\Models\DiagnosticModule;
use App\Models\QuestionDefinition;
use App\Models\QuestionVersion;
use App\Models\ToolField;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationCatalogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_publishes_a_complete_idempotent_catalog_from_the_existing_tools(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $this->seed(ConsultationCatalogSeeder::class);

        $blueprint = ConsultationBlueprint::where('key', 'smart-marketing-consultation')->firstOrFail();

        $this->assertSame('published', $blueprint->status);
        $this->assertSame('published', $blueprint->currentVersion->status);
        $this->assertSame(19, DiagnosticModule::count());
        $this->assertSame(
            ToolField::whereHas('toolVersion.tool', fn ($query) => $query->where('status', 'published'))->count(),
            QuestionDefinition::whereNotNull('legacy_tool_field_id')->count(),
        );
        $this->assertDatabaseHas('question_definitions', ['key' => 'START-05']);
        $this->assertDatabaseHas('question_definitions', ['key' => 'MARKETING-SCORE.VALUE_PROPOSITION']);

        $this->assertSame(3, $blueprint->currentVersion->version);

        $gateway = QuestionVersion::query()
            ->whereHas('definition', fn ($query) => $query->where('key', 'START-01'))
            ->where('version', 3)
            ->firstOrFail();

        $this->assertNotEmpty($gateway->help_text);
        $this->assertNotEmpty($gateway->why_text);
    }
}
