<?php

namespace Tests\Feature;

use App\Models\Objective;
use App\Models\RecommendationTemplate;
use App\Modules\Reporting\Objectives\ObjectiveCatalog;
use Database\Seeders\ReportingContractSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportingContractSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_diagnostic_tool_has_a_seeded_default_objective_and_template(): void
    {
        $this->seed(ReportingContractSeeder::class);
        $catalog = app(ObjectiveCatalog::class);
        $tools = collect(glob(database_path('data/tools/*.php')))
            ->map(fn (string $path) => (require $path)['key'])
            ->sort()
            ->values();

        foreach ($tools as $tool) {
            $slug = $catalog->defaultForTool($tool);
            $this->assertNotNull($slug, "Tool [{$tool}] has no default objective.");
            $objective = Objective::where('slug', $slug)->first();
            $this->assertNotNull($objective, "Objective [{$slug}] was not seeded.");
            $this->assertTrue(
                RecommendationTemplate::where('objective_id', $objective->id)->where('active', true)->exists(),
                "Objective [{$slug}] has no active template.",
            );
            $this->assertContains($slug, $catalog->allowedForTool($tool));
        }
    }
}
