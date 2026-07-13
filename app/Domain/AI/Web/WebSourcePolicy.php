<?php

namespace App\Domain\AI\Web;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class WebSourcePolicy
{
    public function __construct(private readonly int $freshnessDays = 7) {}

    /**
     * @return array{trust_tier: string, trust_score: int, freshness_status: string, valid_until: ?string}
     */
    public function assess(string $url, ?DateTimeInterface $publishedAt, bool $declaredOfficial = false): array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        [$tier, $score] = $declaredOfficial
            ? ['official', 95]
            : ($this->isInstitutional($host) ? ['institutional', 90] : ['unknown', 50]);

        $freshness = 'unknown';
        $validUntil = CarbonImmutable::now()->addDays(max(1, $this->freshnessDays))->utc()->toIso8601String();
        if ($publishedAt !== null) {
            $published = CarbonImmutable::instance($publishedAt);
            $valid = $published->addDays(max(1, $this->freshnessDays));
            $freshness = $valid->isPast() ? 'stale' : 'fresh';
        }

        return [
            'trust_tier' => $tier,
            'trust_score' => $score,
            'freshness_status' => $freshness,
            'valid_until' => $validUntil,
        ];
    }

    private function isInstitutional(string $host): bool
    {
        return preg_match('/(^|\.)(gov|edu)(\.[a-z]{2,})+$/i', $host) === 1
            || str_ends_with($host, '.gov')
            || str_ends_with($host, '.edu');
    }
}
