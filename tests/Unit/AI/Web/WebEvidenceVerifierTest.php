<?php

namespace Tests\Unit\AI\Web;

use App\Domain\AI\Web\WebEvidenceVerifier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebEvidenceVerifierTest extends TestCase
{
    #[Test]
    public function it_verifies_matching_claims_from_independent_fresh_domains(): void
    {
        $result = (new WebEvidenceVerifier)->verify([
            $this->claim('market_growth', '12%', 'stats.gov.sa'),
            $this->claim('market_growth', '12%', 'industry.example'),
        ]);

        $this->assertSame('verified', $result[0]->status);
        $this->assertFalse($result[0]->mustAbstain);
        $this->assertSame(['industry.example', 'stats.gov.sa'], $result[0]->supportingDomains);
    }

    #[Test]
    public function it_keeps_conflicts_visible_and_requires_abstention(): void
    {
        $result = (new WebEvidenceVerifier)->verify([
            $this->claim('market_growth', '12%', 'stats.gov.sa'),
            $this->claim('market_growth', '8%', 'industry.example'),
        ]);

        $this->assertSame('conflict', $result[0]->status);
        $this->assertTrue($result[0]->mustAbstain);
        $this->assertCount(2, $result[0]->values);
    }

    #[Test]
    public function it_does_not_treat_one_domain_or_stale_evidence_as_corroboration(): void
    {
        $result = (new WebEvidenceVerifier)->verify([
            $this->claim('price', '100', 'vendor.example'),
            $this->claim('price', '100', 'vendor.example'),
            $this->claim('price', '100', 'archive.example', 'stale'),
        ]);

        $this->assertSame('unverified', $result[0]->status);
        $this->assertTrue($result[0]->mustAbstain);
    }

    private function claim(string $key, string $value, string $domain, string $freshness = 'fresh'): array
    {
        return [
            'claim_key' => $key,
            'claim_value' => $value,
            'domain' => $domain,
            'trust_score' => 80,
            'freshness_status' => $freshness,
        ];
    }
}
