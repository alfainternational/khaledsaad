<?php

namespace Tests\Feature\AI\Web;

use App\Domain\AI\Web\WebEvidenceVerifier;
use App\Support\Intelligence\RemotePageFetcher;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebResearchEvaluationTest extends TestCase
{
    #[Test]
    public function evaluation_gate_requires_independent_sources_and_abstains_on_conflict(): void
    {
        $verifier = new WebEvidenceVerifier;
        $verified = $verifier->verify([
            $this->claim('growth', '12%', 'official.gov.sa'),
            $this->claim('growth', '12%', 'industry.example'),
        ])[0];
        $conflict = $verifier->verify([
            $this->claim('growth', '12%', 'official.gov.sa'),
            $this->claim('growth', '9%', 'industry.example'),
        ])[0];

        $this->assertSame('verified', $verified->status);
        $this->assertCount(2, $verified->supportingDomains);
        $this->assertSame('conflict', $conflict->status);
        $this->assertTrue($conflict->mustAbstain);
    }

    #[Test]
    public function evaluation_gate_rejects_private_network_fetches_without_an_http_request(): void
    {
        Http::fake();

        $result = (new RemotePageFetcher)->fetch('http://169.254.169.254/latest/meta-data');

        $this->assertFalse($result['ok']);
        $this->assertSame('blocked_private_ip', $result['error']);
        Http::assertNothingSent();
    }

    private function claim(string $key, string $value, string $domain): array
    {
        return [
            'claim_key' => $key, 'claim_value' => $value, 'domain' => $domain,
            'trust_score' => 80, 'freshness_status' => 'fresh',
        ];
    }
}
