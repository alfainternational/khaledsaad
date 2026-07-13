<?php

namespace App\Domain\AI\Web;

class WebEvidenceVerifier
{
    /**
     * @param  array<int, array<string, mixed>>  $claims
     * @return array<int, VerifiedWebFinding>
     */
    public function verify(array $claims): array
    {
        $groups = [];
        foreach ($claims as $claim) {
            $key = trim((string) ($claim['claim_key'] ?? ''));
            $value = $this->normalizeValue((string) ($claim['claim_value'] ?? ''));
            $domain = strtolower(trim((string) ($claim['domain'] ?? '')));
            if ($key === '' || $value === '' || $domain === '') {
                continue;
            }

            if (($claim['freshness_status'] ?? 'unknown') !== 'fresh' || (int) ($claim['trust_score'] ?? 0) < 50) {
                continue;
            }

            $groups[$key][$value][$domain] = true;
        }

        $allKeys = array_values(array_unique(array_filter(array_map(
            static fn (array $claim): string => trim((string) ($claim['claim_key'] ?? '')),
            $claims,
        ))));
        sort($allKeys, SORT_STRING);

        $findings = [];
        foreach ($allKeys as $key) {
            $values = [];
            foreach ($groups[$key] ?? [] as $value => $domains) {
                $domainList = array_keys($domains);
                sort($domainList, SORT_STRING);
                $values[$value] = $domainList;
            }
            ksort($values, SORT_STRING);

            $status = 'unverified';
            $supportingDomains = [];
            if (count($values) > 1) {
                $status = 'conflict';
            } elseif (count($values) === 1) {
                $supportingDomains = array_values(reset($values));
                if (count($supportingDomains) >= 2) {
                    $status = 'verified';
                }
            }

            $findings[] = new VerifiedWebFinding(
                claimKey: $key,
                status: $status,
                mustAbstain: $status !== 'verified',
                values: $values,
                supportingDomains: $supportingDomains,
            );
        }

        return $findings;
    }

    private function normalizeValue(string $value): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));
    }
}
