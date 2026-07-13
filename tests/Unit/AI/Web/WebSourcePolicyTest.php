<?php

namespace Tests\Unit\AI\Web;

use App\Domain\AI\Web\WebSourcePolicy;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebSourcePolicyTest extends TestCase
{
    #[Test]
    public function it_scores_declared_official_and_institutional_sources_above_unknown_sources(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $policy = new WebSourcePolicy(7);

        $official = $policy->assess('https://vendor.example/pricing', now()->subDay(), true);
        $government = $policy->assess('https://stats.gov.sa/report', now()->subDays(2));
        $unknown = $policy->assess('https://random-blog.example/post', now()->subDays(2));

        $this->assertSame('official', $official['trust_tier']);
        $this->assertGreaterThan($unknown['trust_score'], $official['trust_score']);
        $this->assertSame('institutional', $government['trust_tier']);
        $this->assertGreaterThan($unknown['trust_score'], $government['trust_score']);
    }

    #[Test]
    public function it_marks_old_or_undated_evidence_without_calling_it_current(): void
    {
        Carbon::setTestNow('2026-07-13 12:00:00');
        $policy = new WebSourcePolicy(7);

        $this->assertSame('fresh', $policy->assess('https://example.com', now()->subDays(6))['freshness_status']);
        $stale = $policy->assess('https://example.com', now()->subDays(8));
        $this->assertSame('stale', $stale['freshness_status']);
        $this->assertSame(now()->addDays(7)->utc()->toIso8601String(), $stale['valid_until']);
        $undated = $policy->assess('https://example.com', null);
        $this->assertSame('unknown', $undated['freshness_status']);
        $this->assertSame(now()->addDays(7)->utc()->toIso8601String(), $undated['valid_until']);
    }
}
