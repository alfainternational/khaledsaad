<?php

namespace Tests\Unit\AI\Web;

use App\Domain\AI\Kernel\AgentContext;
use App\Domain\AI\Kernel\Skills\WebResearchSkill;
use App\Domain\AI\Web\WebResearchService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebResearchSkillTest extends TestCase
{
    #[Test]
    public function it_exposes_citations_and_reduces_confidence_for_unverified_evidence(): void
    {
        $research = Mockery::mock(WebResearchService::class);
        $research->shouldReceive('research')->once()->andReturn([
            'query' => 'السوق',
            'summary' => 'نتائج أولية غير مؤكدة',
            'categories' => ['market' => 1],
            'findings' => [[
                'title' => 'تقرير السوق',
                'url' => 'https://example.com/report',
                'category' => 'market',
                'verification_status' => 'unverified',
                'citation' => ['url' => 'https://example.com/report', 'fetched_at' => '2026-07-13T00:00:00+00:00'],
            ]],
        ]);

        $result = (new WebResearchSkill($research))->run(new AgentContext(
            intent: 'web_research',
            signals: ['query' => 'السوق'],
        ));

        $this->assertSame(45, $result->confidence);
        $this->assertStringContainsString('https://example.com/report', $result->bullets[0]);
        $this->assertSame('unverified', $result->meta['findings'][0]['verification_status']);
    }
}
