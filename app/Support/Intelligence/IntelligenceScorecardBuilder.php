<?php

namespace App\Support\Intelligence;

class IntelligenceScorecardBuilder
{
    public function __construct(
        private readonly SectorTemplateCatalog $sectors,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $projectFindings
     * @param  array<int, array<string, mixed>>  $competitorSummaries
     * @return array<string, mixed>
     */
    public function build(string $sector, array $projectFindings, array $competitorSummaries = []): array
    {
        $template = $this->sectors->for($sector);
        $weights = $template['weights'] ?? [];

        $scores = [
            'website' => $this->dimensionScore('website', $projectFindings),
            'social' => $this->dimensionScore('social', $projectFindings),
            'seo' => $this->dimensionScore('seo', $projectFindings),
            'trust' => $this->dimensionScore('trust', $projectFindings),
            'conversion' => $this->dimensionScore('conversion', $projectFindings),
            'ads_readiness' => $this->dimensionScore('ads_readiness', $projectFindings),
            'ai_visibility' => $this->dimensionScore('ai_visibility', $projectFindings),
            'competition' => $this->competitionScore($competitorSummaries),
            'lead_readiness' => $this->dimensionScore('lead_readiness', $projectFindings),
        ];

        $executive = 0;
        foreach ($weights as $code => $weight) {
            $executive += ($scores[$code] ?? 0) * (float) $weight;
        }

        return [
            'executive_score' => (int) round($executive),
            'scores' => $scores,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $findings
     */
    private function dimensionScore(string $dimension, array $findings): int
    {
        $score = 100;
        foreach ($findings as $finding) {
            if (($finding['area'] ?? null) !== $dimension) {
                continue;
            }
            $score -= (int) ($finding['score_impact'] ?? 0);
        }

        return max(15, min(100, $score));
    }

    /**
     * @param  array<int, array<string, mixed>>  $competitorSummaries
     */
    private function competitionScore(array $competitorSummaries): int
    {
        if ($competitorSummaries === []) {
            return 55;
        }

        $bestCompetitor = collect($competitorSummaries)->max('executive_score');
        $projectAverageGap = collect($competitorSummaries)->avg(function (array $competitor): float {
            return max(0, ((int) ($competitor['executive_score'] ?? 55)) - 65);
        });

        return max(20, min(100, (int) round(80 - (($bestCompetitor > 80 ? 8 : 0) + $projectAverageGap))));
    }
}
