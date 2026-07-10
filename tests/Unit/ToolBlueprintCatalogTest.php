<?php

namespace Tests\Unit;

use App\Support\Tooling\ToolBlueprintCatalog;
use PHPUnit\Framework\TestCase;

class ToolBlueprintCatalogTest extends TestCase
{
    public function test_all_seeded_tools_have_explicit_blueprints(): void
    {
        $catalog = new ToolBlueprintCatalog();

        $codes = [
            'diagnosis',
            'idea-clarity',
            'swot-analysis',
            'goal-definition',
            'problem-definition',
            'tagline-builder',
            'ideal-customer',
            'positioning',
            'market-analysis',
            'competitor-analysis',
            'offer-builder',
            'pricing-strategy',
            'value-ladder',
            'package-builder',
            'promise-builder',
            'funnel-builder',
            'customer-journey',
            'marketing-plan',
            'content-plan',
            'campaign-builder',
            'follow-up-sequence',
            'kpi-tracker',
            'execution-plan',
            'performance-review',
            'agency-audit',
            'smart-recommendations',
            'growth-priorities',
        ];

        foreach ($codes as $code) {
            $blueprint = $catalog->for($code);

            $this->assertIsArray($blueprint);
            $this->assertNotSame('مخرج '.$code, $blueprint['result_label'] ?? null);
            $this->assertNotEmpty($blueprint['intro'] ?? null);
            $this->assertNotEmpty($blueprint['why'] ?? null);
            $this->assertNotEmpty($blueprint['when'] ?? null);
            $this->assertNotEmpty($blueprint['ai_role'] ?? null);
            $this->assertArrayHasKey('guided', $blueprint['modes']);
            $this->assertArrayNotHasKey('structured', $blueprint['modes']);
            $this->assertArrayNotHasKey('expert', $blueprint['modes']);
            $this->assertNotEmpty($blueprint['modes']['guided']['fields']);
            $this->assertNotEmpty($blueprint['modes']['guided']['fields'][0]['answer_tip'] ?? null);
            $this->assertContains('core', array_column($blueprint['modes']['guided']['fields'], 'depth'));
            $this->assertContains('detail', array_column($blueprint['modes']['guided']['fields'], 'depth'));
        }
    }
}
