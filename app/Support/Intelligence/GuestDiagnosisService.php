<?php

namespace App\Support\Intelligence;

use App\Domain\Intelligence\Models\DiagnosisCase;

/**
 * Runs a quick, in-house diagnosis for a pre-registration guest by reusing the very
 * same analyzers that power the authenticated audit engine — NO external paid data API
 * (data-sovereignty decision). Produces a full internal report plus a gated "partial"
 * view shown to the lead before registration.
 */
class GuestDiagnosisService
{
    public function __construct(
        private readonly WebsiteAuditAnalyzer $websiteAuditAnalyzer,
        private readonly SocialAuditAnalyzer $socialAuditAnalyzer,
        private readonly IntelligenceScorecardBuilder $scorecardBuilder,
        private readonly HonestDiagnosisComposer $diagnosisComposer,
    ) {}

    /**
     * @return array{executive_score:int, integrity_status:string, report:array<string,mixed>, partial:array<string,mixed>}
     */
    public function analyze(DiagnosisCase $case): array
    {
        $sector = $case->sector ?: 'general';
        $url = $case->input_url;

        $website = $this->websiteAuditAnalyzer->analyze($url, $sector, []);
        $social = $this->socialAuditAnalyzer->analyze(
            $website['discovered_social_links'] ?? [],
            $url,
        );

        $findings = array_merge($website['findings'] ?? [], $social['findings'] ?? []);
        $contacts = $website['contacts'] ?? [];

        // One free competitor comparison (the strongest funnel hook — see competitive research).
        $competitorSummaries = [];
        $competitorDomain = $this->firstCompetitor($case->competitors_json ?? []);
        if ($competitorDomain !== null) {
            $cWebsite = $this->websiteAuditAnalyzer->analyze($competitorDomain, $sector, []);
            $cSocial = $this->socialAuditAnalyzer->analyze($cWebsite['discovered_social_links'] ?? [], $competitorDomain);
            $cFindings = array_merge($cWebsite['findings'] ?? [], $cSocial['findings'] ?? []);
            $cScore = $this->scorecardBuilder->build($sector, $cFindings);
            $competitorSummaries[] = [
                'label' => $competitorDomain,
                'executive_score' => $cScore['executive_score'],
                'scores' => $cScore['scores'],
            ];
        }

        $scoreSummary = $this->scorecardBuilder->build($sector, $findings, $competitorSummaries);
        $integrity = $this->integrity($website, $social, $findings, $competitorSummaries, filled($url));

        $diagnosis = $this->diagnosisComposer->compose(
            $scoreSummary['scores'],
            $findings,
            $competitorSummaries,
            $contacts,
            [],
            ['status' => $integrity],
        );

        $report = [
            'executive_scores' => [
                'executive' => $scoreSummary['executive_score'],
                ...$scoreSummary['scores'],
            ],
            'analysis_integrity' => ['status' => $integrity],
            'primary_snapshot' => [
                'website' => $website['snapshot'] ?? [],
                'social' => $social['profiles'] ?? [],
            ],
            'findings' => $findings,
            'competitor_summaries' => $competitorSummaries,
            'contacts' => $contacts,
            ...$diagnosis,
        ];

        return [
            'executive_score' => (int) $scoreSummary['executive_score'],
            'integrity_status' => $integrity,
            'report' => $report,
            'partial' => $this->buildPartial($scoreSummary, $integrity, $findings, $competitorSummaries),
        ];
    }

    /**
     * The gated, public-safe slice shown to the lead. Detailed/actionable content stays locked.
     *
     * @param  array<string,mixed>  $scoreSummary
     * @param  array<int,array<string,mixed>>  $findings
     * @param  array<int,array<string,mixed>>  $competitorSummaries
     * @return array<string,mixed>
     */
    private function buildPartial(array $scoreSummary, string $integrity, array $findings, array $competitorSummaries): array
    {
        $trusted = collect($findings)
            ->filter(fn (array $f): bool => (float) ($f['confidence'] ?? 0) >= 0.6)
            ->sortByDesc(fn (array $f): int => (int) ($f['score_impact'] ?? 0))
            ->values();

        $topThree = $trusted->take(3)->map(fn (array $f): array => [
            'title' => (string) ($f['title'] ?? ''),
            'area' => (string) ($f['area'] ?? ''),
            'severity' => (string) ($f['severity'] ?? 'medium'),
        ])->all();

        $opportunity = $trusted->first();

        $comparison = null;
        if ($competitorSummaries !== []) {
            $comparison = [
                'you' => (int) $scoreSummary['executive_score'],
                'competitor_label' => (string) ($competitorSummaries[0]['label'] ?? 'منافس'),
                'competitor' => (int) ($competitorSummaries[0]['executive_score'] ?? 0),
            ];
        }

        $lockedCount = max(0, $trusted->count() - count($topThree));

        return [
            'executive_score' => (int) $scoreSummary['executive_score'],
            'integrity_status' => $integrity,
            'top_problems' => $topThree,
            'immediate_opportunity' => $opportunity ? (string) ($opportunity['recommendation'] ?? '') : null,
            'competitor_comparison' => $comparison,
            'locked_problems_count' => $lockedCount,
        ];
    }

    /**
     * Simplified, in-house integrity status mirroring MarketingIntelligenceService.
     *
     * @param  array<string,mixed>  $website
     * @param  array<string,mixed>  $social
     * @param  array<int,array<string,mixed>>  $findings
     * @param  array<int,array<string,mixed>>  $competitorSummaries
     */
    private function integrity(array $website, array $social, array $findings, array $competitorSummaries, bool $hasUrl): string
    {
        $websiteReadable = (bool) ($website['snapshot']['ok'] ?? false);
        $socialAccessible = count($social['profiles'] ?? []);
        $highConfidence = count(array_filter($findings, fn (array $f): bool => (float) ($f['confidence'] ?? 0) >= 0.7));

        $signals = ($websiteReadable ? 2 : 0)
            + ($socialAccessible > 0 ? 1 : 0)
            + ($competitorSummaries !== [] ? 1 : 0);

        return match (true) {
            $websiteReadable && $highConfidence >= 3 && $signals >= 4 => 'verified',
            $hasUrl && ! $websiteReadable => 'insufficient',
            $websiteReadable || $socialAccessible > 0 || $highConfidence >= 2 => 'partial',
            default => 'insufficient',
        };
    }

    /**
     * @param  array<int,mixed>  $competitors
     */
    private function firstCompetitor(array $competitors): ?string
    {
        foreach ($competitors as $competitor) {
            $value = is_array($competitor) ? ($competitor['domain'] ?? $competitor['label'] ?? null) : $competitor;
            $value = is_string($value) ? trim($value) : '';
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
