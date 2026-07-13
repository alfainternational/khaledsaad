<?php

namespace App\Domain\AI\Web;

use App\Contracts\WebSearchGateway;

class CompositeWebSearchGateway implements WebSearchGateway
{
    /**
     * @param  iterable<string, WebSearchGateway>  $gateways
     */
    public function __construct(
        private readonly iterable $gateways,
        private readonly WebSearchResultNormalizer $normalizer,
        private readonly int $perDomainLimit = 2,
    ) {}

    public function search(string $query, int $limit = 5): array
    {
        $limit = max(1, $limit);
        $results = [];
        $seen = [];
        $domainCounts = [];

        foreach ($this->gateways as $provider => $gateway) {
            try {
                $providerResults = $gateway->search($query, max($limit * 2, 10));
            } catch (\Throwable) {
                continue;
            }

            foreach ($providerResults as $candidate) {
                if (! is_array($candidate) || ($normalized = $this->normalizer->normalize($candidate)) === null) {
                    continue;
                }

                $hash = hash('sha256', $normalized['url']);
                $domain = strtolower((string) parse_url($normalized['url'], PHP_URL_HOST));
                if (isset($seen[$hash]) || ($domainCounts[$domain] ?? 0) >= max(1, $this->perDomainLimit)) {
                    continue;
                }

                $seen[$hash] = true;
                $domainCounts[$domain] = ($domainCounts[$domain] ?? 0) + 1;
                $results[] = $normalized + ['provider' => (string) $provider];

                if (count($results) >= $limit) {
                    return $results;
                }
            }
        }

        return $results;
    }
}
