<?php

namespace App\Domain\AI\Web;

final readonly class VerifiedWebFinding
{
    /**
     * @param  array<string, array<int, string>>  $values
     * @param  array<int, string>  $supportingDomains
     */
    public function __construct(
        public string $claimKey,
        public string $status,
        public bool $mustAbstain,
        public array $values,
        public array $supportingDomains,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'claim_key' => $this->claimKey,
            'status' => $this->status,
            'must_abstain' => $this->mustAbstain,
            'values' => $this->values,
            'supporting_domains' => $this->supportingDomains,
        ];
    }
}
