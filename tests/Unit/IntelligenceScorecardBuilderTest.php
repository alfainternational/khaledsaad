<?php

namespace Tests\Unit;

use App\Support\Intelligence\IntelligenceScorecardBuilder;
use App\Support\Intelligence\SectorTemplateCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntelligenceScorecardBuilderTest extends TestCase
{
    #[Test]
    public function it_applies_sector_weighting_and_competitor_pressure(): void
    {
        $builder = new IntelligenceScorecardBuilder(new SectorTemplateCatalog);

        $findings = [
            ['area' => 'website', 'score_impact' => 5],
            ['area' => 'conversion', 'score_impact' => 35],
            ['area' => 'trust', 'score_impact' => 5],
        ];

        $general = $builder->build('general_business', $findings);
        $ecommerce = $builder->build('ecommerce', $findings, [
            ['label' => 'Competitor A', 'executive_score' => 100],
            ['label' => 'Competitor B', 'executive_score' => 92],
        ]);

        $this->assertSame(95, $general['scores']['website']);
        $this->assertSame(65, $general['scores']['conversion']);
        $this->assertSame(95, $general['scores']['trust']);
        $this->assertLessThan($general['executive_score'], $ecommerce['executive_score']);
        $this->assertLessThan(55, $ecommerce['scores']['competition']);
    }

    #[Test]
    public function it_keeps_dimension_scores_within_the_supported_bounds(): void
    {
        $builder = new IntelligenceScorecardBuilder(new SectorTemplateCatalog);

        $result = $builder->build('saas', [
            ['area' => 'website', 'score_impact' => 999],
            ['area' => 'seo', 'score_impact' => 999],
        ]);

        $this->assertSame(15, $result['scores']['website']);
        $this->assertSame(15, $result['scores']['seo']);
        $this->assertGreaterThanOrEqual(15, $result['executive_score']);
    }
}
