<?php

namespace App\Support\Intelligence;

use App\Domain\Intelligence\Models\AuditFinding;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Intelligence\Models\AuditTarget;
use App\Domain\Intelligence\Models\MonitorSnapshot;
use App\Domain\Intelligence\Models\OfficialContact;
use App\Domain\Intelligence\Models\Scorecard;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingIntelligenceService
{
    public function __construct(
        private readonly WebsiteAuditAnalyzer $websiteAuditAnalyzer,
        private readonly SocialAuditAnalyzer $socialAuditAnalyzer,
        private readonly IntelligenceScorecardBuilder $scorecardBuilder,
        private readonly HonestDiagnosisComposer $diagnosisComposer,
    ) {}

    public function activeRun(Project $project): ?AuditRun
    {
        return AuditRun::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();
    }

    public function queue(Project $project, Workspace $workspace, string $triggerSource = 'manual'): AuditRun
    {
        return AuditRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'status' => 'queued',
            'trigger_source' => $triggerSource,
            'summary_json' => [
                'headline' => 'تمت جدولة تقرير intelligence',
            ],
        ]);
    }

    public function execute(AuditRun $auditRun): AuditRun
    {
        if ($auditRun->status === 'completed') {
            return $auditRun->fresh(['targets', 'findings', 'scorecards', 'officialContacts']);
        }

        $project = $auditRun->project()->with('workspace')->first();

        if (! $project || ! $project->workspace) {
            return $this->failRun(
                $auditRun,
                'missing_context',
                'تعذر العثور على المشروع أو مساحة العمل المرتبطة بهذا التحليل.',
            );
        }

        $workspace = $project->workspace;
        $this->markRunning($auditRun);

        try {
            $analysis = $this->collectAnalysis($project);
            $this->persistCompletedAnalysis($auditRun, $project, $workspace, $analysis);
        } catch (\Throwable $exception) {
            report($exception);

            return $this->failRun(
                $auditRun,
                'analysis_failed',
                $exception->getMessage(),
                ['exception' => $exception::class],
            );
        }

        return $auditRun->fresh(['targets', 'findings', 'scorecards', 'officialContacts']);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectAnalysis(Project $project): array
    {
        $primaryWebsite = $this->websiteAuditAnalyzer->analyze(
            $project->primary_domain,
            (string) $project->sector,
            $project->official_social_links_json ?? [],
        );

        $primarySocial = $this->socialAuditAnalyzer->analyze(
            $primaryWebsite['discovered_social_links'] ?? ($project->official_social_links_json ?? []),
            $project->primary_domain,
            $project->verified_social_profiles_json ?? [],
        );

        $primaryFindings = array_merge($primaryWebsite['findings'] ?? [], $primarySocial['findings'] ?? []);
        $primaryContacts = $primaryWebsite['contacts'] ?? [];
        $allProjectFindings = $primaryFindings;

        $competitorSummaries = [];
        $competitorAnalyses = [];
        $competitorStats = [
            'requested' => 0,
            'analyzed' => 0,
            'skipped' => 0,
            'warnings' => [],
        ];

        $competitors = $this->normalizedCompetitors($project->competitors_json ?? []);
        $competitorStats['requested'] = count($competitors);

        foreach ($competitors as $competitor) {
            if ($competitor['domain'] === null && $competitor['social_links'] === []) {
                $competitorStats['skipped']++;
                $competitorStats['warnings'][] = 'تم تجاهل منافس "'.$competitor['label'].'" لأن بياناته لا تحتوي دوميناً أو روابط قابلة للتحليل.';

                continue;
            }

            $website = $this->websiteAuditAnalyzer->analyze(
                $competitor['domain'],
                (string) $project->sector,
                $competitor['social_links'],
            );
            $social = $this->socialAuditAnalyzer->analyze(
                $website['discovered_social_links'] ?? $competitor['social_links'],
                $competitor['domain'],
            );
            $findings = array_merge($website['findings'] ?? [], $social['findings'] ?? []);
            $scores = $this->scorecardBuilder->build((string) $project->sector, $findings);

            $competitorStats['analyzed']++;
            $competitorSummaries[] = [
                'label' => $competitor['label'],
                'executive_score' => $scores['executive_score'],
                'scores' => $scores['scores'],
            ];
            $competitorAnalyses[] = [
                'label' => $competitor['label'],
                'domain' => $competitor['domain'],
                'page_url' => $competitor['domain'],
                'social_links_json' => $website['discovered_social_links'] ?? $competitor['social_links'],
                'snapshot_json' => [
                    'website' => $website['snapshot'] ?? [],
                    'social' => $social['profiles'] ?? [],
                ],
                'findings' => $findings,
                'scores' => $scores['scores'],
            ];
        }

        $scoreSummary = $this->scorecardBuilder->build((string) $project->sector, $allProjectFindings, $competitorSummaries);
        $trend = $this->monitoringTrend($project);
        $evidence = $this->buildAnalysisIntegrity(
            $project,
            $primaryWebsite,
            $primarySocial,
            $primaryContacts,
            $allProjectFindings,
            $competitorStats,
        );
        $diagnosis = $this->diagnosisComposer->compose(
            $scoreSummary['scores'],
            $allProjectFindings,
            $competitorSummaries,
            $primaryContacts,
            $trend,
            $evidence,
        );

        $report = [
            'executive_scores' => [
                'executive' => $scoreSummary['executive_score'],
                ...$scoreSummary['scores'],
            ],
            'analysis_integrity' => $evidence,
            'domain_scorecards' => [
                'primary' => [
                    'website' => $primaryWebsite['snapshot'] ?? [],
                    'social' => $primarySocial['profiles'] ?? [],
                ],
                'competitors' => $competitorSummaries,
            ],
            'official_contacts' => $primaryContacts,
            ...$diagnosis,
        ];

        return [
            'primary_social_links' => $primaryWebsite['discovered_social_links'] ?? ($project->official_social_links_json ?? []),
            'primary_snapshot' => [
                'website' => $primaryWebsite['snapshot'] ?? [],
                'social' => $primarySocial['profiles'] ?? [],
            ],
            'primary_findings' => $primaryFindings,
            'primary_contacts' => $primaryContacts,
            'competitor_analyses' => $competitorAnalyses,
            'score_summary' => $scoreSummary,
            'report' => $report,
            'summary' => [
                'headline' => match ($evidence['status']) {
                    'verified' => 'تقرير intelligence موثّق وجاهز',
                    'partial' => 'تقرير intelligence جزئي',
                    default => 'تقرير intelligence أولي منخفض الثقة',
                },
                'executive_score' => $scoreSummary['executive_score'],
            ],
            'payload' => [
                'primary_snapshot' => [
                    'website' => $primaryWebsite['snapshot'] ?? [],
                    'social' => $primarySocial['profiles'] ?? [],
                ],
                'raw_metrics' => $primaryWebsite['raw_metrics'] ?? [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $analysis
     */
    private function persistCompletedAnalysis(AuditRun $auditRun, Project $project, Workspace $workspace, array $analysis): void
    {
        DB::transaction(function () use ($auditRun, $project, $workspace, $analysis): void {
            $auditRun->findings()->delete();
            $auditRun->scorecards()->delete();
            $auditRun->officialContacts()->delete();
            $auditRun->targets()->delete();

            $primaryTarget = AuditTarget::query()->create([
                'audit_run_id' => $auditRun->id,
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'kind' => 'primary',
                'label' => $project->name,
                'domain' => $project->primary_domain,
                'page_url' => $project->primary_domain,
                'social_links_json' => $analysis['primary_social_links'],
                'status' => 'completed',
                'snapshot_json' => $analysis['primary_snapshot'],
            ]);

            foreach ($analysis['primary_findings'] as $finding) {
                $this->persistFinding($auditRun, $primaryTarget, $workspace, $project, $finding);
            }

            foreach ($analysis['primary_contacts'] as $contact) {
                $this->persistContact($auditRun, $primaryTarget, $workspace, $project, $contact);
            }

            foreach ($analysis['competitor_analyses'] as $competitorAnalysis) {
                $target = AuditTarget::query()->create([
                    'audit_run_id' => $auditRun->id,
                    'workspace_id' => $workspace->id,
                    'project_id' => $project->id,
                    'kind' => 'competitor',
                    'label' => $competitorAnalysis['label'],
                    'domain' => $competitorAnalysis['domain'],
                    'page_url' => $competitorAnalysis['page_url'],
                    'social_links_json' => $competitorAnalysis['social_links_json'],
                    'status' => 'completed',
                    'snapshot_json' => $competitorAnalysis['snapshot_json'],
                ]);

                foreach ($competitorAnalysis['findings'] as $finding) {
                    $this->persistFinding($auditRun, $target, $workspace, $project, $finding);
                }

                $this->persistTargetScorecards($auditRun, $target, $workspace, $project, $competitorAnalysis['scores'], 'target');
            }

            $scoreSummary = $analysis['score_summary'];
            $report = $analysis['report'];
            $this->persistTargetScorecards($auditRun, $primaryTarget, $workspace, $project, $scoreSummary['scores'], 'target');
            $this->persistTargetScorecards(
                $auditRun,
                null,
                $workspace,
                $project,
                ['executive' => $scoreSummary['executive_score'], ...$scoreSummary['scores']],
                'project',
            );

            $auditRun->update([
                'status' => 'completed',
                'completed_at' => now(),
                'failed_at' => null,
                'summary_json' => $analysis['summary'],
                'report_json' => $report,
                'payload_json' => $analysis['payload'],
                'error_json' => null,
            ]);

            if ($project->monitoring_enabled) {
                MonitorSnapshot::query()->create([
                    'workspace_id' => $workspace->id,
                    'project_id' => $project->id,
                    'audit_run_id' => $auditRun->id,
                    'captured_at' => now(),
                    'executive_score' => $scoreSummary['executive_score'],
                    'website_score' => $scoreSummary['scores']['website'],
                    'social_score' => $scoreSummary['scores']['social'],
                    'seo_score' => $scoreSummary['scores']['seo'],
                    'trust_score' => $scoreSummary['scores']['trust'],
                    'conversion_score' => $scoreSummary['scores']['conversion'],
                    'ads_readiness_score' => $scoreSummary['scores']['ads_readiness'],
                    'ai_visibility_score' => $scoreSummary['scores']['ai_visibility'],
                    'competition_score' => $scoreSummary['scores']['competition'],
                    'lead_readiness_score' => $scoreSummary['scores']['lead_readiness'],
                    'payload_json' => $report,
                ]);
            }
        });
    }

    private function markRunning(AuditRun $auditRun): void
    {
        $auditRun->update([
            'status' => 'running',
            'started_at' => $auditRun->started_at ?? now(),
            'failed_at' => null,
            'error_json' => null,
            'summary_json' => [
                'headline' => 'جارٍ تشغيل تقرير intelligence',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function failRun(
        AuditRun $auditRun,
        string $code,
        string $message,
        array $meta = [],
    ): AuditRun {
        $auditRun->update([
            'status' => 'failed',
            'failed_at' => now(),
            'summary_json' => [
                'headline' => 'فشل تشغيل تقرير intelligence',
            ],
            'error_json' => [
                'code' => $code,
                'message' => Str::limit(trim($message) !== '' ? $message : 'حدث خطأ غير متوقع أثناء تشغيل التحليل.', 400),
                'meta' => $meta,
            ],
        ]);

        return $auditRun->fresh(['targets', 'findings', 'scorecards', 'officialContacts']);
    }

    /**
     * @param  array<string, mixed>  $primaryWebsite
     * @param  array<string, mixed>  $primarySocial
     * @param  array<int, array<string, mixed>>  $contacts
     * @param  array<int, array<string, mixed>>  $projectFindings
     * @param  array{requested: int, analyzed: int, skipped: int, warnings: array<int, string>}  $competitorStats
     * @return array<string, mixed>
     */
    private function buildAnalysisIntegrity(
        Project $project,
        array $primaryWebsite,
        array $primarySocial,
        array $contacts,
        array $projectFindings,
        array $competitorStats,
    ): array {
        $websiteReadable = (bool) ($primaryWebsite['snapshot']['ok'] ?? false);
        $socialRequested = (int) ($primarySocial['analysis_meta']['requested_profiles'] ?? count($project->official_social_links_json ?? []));
        $socialAccessible = (int) ($primarySocial['analysis_meta']['accessible_profiles'] ?? count($primarySocial['profiles'] ?? []));
        $socialAutomatedAccessible = (int) ($primarySocial['analysis_meta']['automated_accessible_profiles'] ?? $socialAccessible);
        $socialManualVerified = (int) ($primarySocial['analysis_meta']['manual_verified_profiles'] ?? 0);
        $verifiedContacts = count(array_filter($contacts, fn (array $contact): bool => (bool) ($contact['is_verified'] ?? false)));
        $highConfidenceFindings = count(array_filter($projectFindings, fn (array $finding): bool => (float) ($finding['confidence'] ?? 0) >= 0.7));
        $hasPrimaryDomain = filled($project->primary_domain);

        $signals = 0;
        if ($websiteReadable) {
            $signals += 2;
        }
        if ($socialAccessible > 0) {
            $signals++;
        }
        if ($verifiedContacts > 0) {
            $signals++;
        }
        if ($competitorStats['analyzed'] > 0) {
            $signals++;
        }

        $status = match (true) {
            $websiteReadable && $highConfidenceFindings >= 3 && $signals >= 4 => 'verified',
            $hasPrimaryDomain && ! $websiteReadable => 'insufficient',
            $websiteReadable || $socialAccessible > 0 || $verifiedContacts > 0 || $highConfidenceFindings >= 2 => 'partial',
            default => 'insufficient',
        };

        $warnings = [];

        if (! $websiteReadable) {
            $warnings[] = 'تعذر الوصول إلى الموقع الأساسي أو قراءة الصفحة الرئيسية بشكل كافٍ.';
        }

        if ($socialRequested === 0) {
            $warnings[] = 'لا توجد روابط سوشيال رسمية مؤكدة ضمن المشروع.';
        } elseif ($socialAccessible === 0) {
            $warnings[] = 'تمت محاولة قراءة السوشيال لكن لم يتم الوصول إلى أي صفحة عامة قابلة للتحليل.';
        } elseif ($socialAutomatedAccessible === 0 && $socialManualVerified > 0) {
            $warnings[] = 'تعذرت القراءة الآلية للسوشيال وتم الاعتماد على تحقق يدوي موثق لبعض الحسابات.';
        }

        if ($verifiedContacts === 0) {
            $warnings[] = 'لم يتم استخراج قنوات تواصل رسمية مؤكدة من المصادر المقروءة.';
        }

        if ($competitorStats['requested'] === 0) {
            $warnings[] = 'لا توجد قائمة منافسين مؤكدة داخل المشروع.';
        } elseif ($competitorStats['analyzed'] === 0) {
            $warnings[] = 'قائمة المنافسين الحالية لم تنتج أي مقارنة قابلة للتحقق.';
        }

        $warnings = [...$warnings, ...$competitorStats['warnings']];

        $label = match ($status) {
            'verified' => 'تحليل مبني على مصادر فعلية',
            'partial' => 'تحليل جزئي يحتاج استكمال',
            default => 'تحليل أولي منخفض الثقة',
        };

        $summary = match ($status) {
            'verified' => 'النتائج مبنية على قراءة فعلية للموقع مع إشارات قابلة للتحقق من السوشيال أو التواصل أو المنافسين.',
            'partial' => 'النتائج الحالية تعتمد على جزء من المصادر فقط، لذا بعض الاستنتاجات إرشادية وليست نهائية.',
            default => 'النتائج الحالية لا تكفي لبناء diagnosis أو action plan موثوق، ويجب استكمال المدخلات أو إتاحة الوصول ثم إعادة التحليل.',
        };

        $highlights = array_values(array_filter([
            $websiteReadable ? 'تم تحليل الموقع الأساسي مباشرة.' : null,
            $socialAccessible > 0 ? 'تم الوصول إلى '.$socialAccessible.' من حسابات السوشيال العامة.' : null,
            $socialManualVerified > 0 ? 'تم اعتماد '.$socialManualVerified.' حساب/حسابات سوشيال موثقة يدوياً كدليل fallback.' : null,
            $verifiedContacts > 0 ? 'تم توثيق '.$verifiedContacts.' قناة تواصل رسمية.' : null,
            $competitorStats['analyzed'] > 0 ? 'تم تحليل '.$competitorStats['analyzed'].' منافس/منافسين فعلياً.' : null,
        ]));

        return [
            'status' => $status,
            'label' => $label,
            'summary' => $summary,
            'highlights' => $highlights,
            'warnings' => array_values(array_unique($warnings)),
            'counts' => [
                'website_readable' => $websiteReadable ? 1 : 0,
                'social_requested' => $socialRequested,
                'social_accessible' => $socialAccessible,
                'social_automated_accessible' => $socialAutomatedAccessible,
                'social_manual_verified' => $socialManualVerified,
                'verified_contacts' => $verifiedContacts,
                'competitors_requested' => $competitorStats['requested'],
                'competitors_analyzed' => $competitorStats['analyzed'],
                'competitors_skipped' => $competitorStats['skipped'],
                'findings_total' => count($projectFindings),
                'high_confidence_findings' => $highConfidenceFindings,
            ],
            'competitor_summary' => $competitorStats['analyzed'] > 0
                ? 'تم جمع مقارنات منافسين من مصادر قابلة للقراءة.'
                : 'لا توجد بيانات منافسين مؤكدة تكفي لصناعة snapshot موثوق.',
        ];
    }

    /**
     * @param  array<int, mixed>  $competitors
     * @return array<int, array{label: string, domain: ?string, social_links: array<int, string>}>
     */
    private function normalizedCompetitors(array $competitors): array
    {
        return collect($competitors)
            ->map(function (mixed $competitor): ?array {
                if (is_string($competitor) && trim($competitor) !== '') {
                    return [
                        'label' => trim($competitor),
                        'domain' => trim($competitor),
                        'social_links' => [],
                    ];
                }

                if (! is_array($competitor)) {
                    return null;
                }

                $label = trim((string) ($competitor['label'] ?? $competitor['domain'] ?? ''));
                $domain = trim((string) ($competitor['domain'] ?? ''));

                if ($label === '' && $domain === '') {
                    return null;
                }

                return [
                    'label' => $label !== '' ? $label : $domain,
                    'domain' => $domain !== '' ? $domain : null,
                    'social_links' => collect($competitor['social_links'] ?? [])->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')->values()->all(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $finding
     */
    private function persistFinding(AuditRun $auditRun, ?AuditTarget $target, Workspace $workspace, Project $project, array $finding): void
    {
        AuditFinding::query()->create([
            'audit_run_id' => $auditRun->id,
            'audit_target_id' => $target?->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'area' => (string) ($finding['area'] ?? 'website'),
            'subcategory' => (string) ($finding['subcategory'] ?? 'general'),
            'severity' => (string) ($finding['severity'] ?? 'medium'),
            'confidence' => (float) ($finding['confidence'] ?? 0),
            'score_impact' => (int) ($finding['score_impact'] ?? 0),
            'title' => (string) ($finding['title'] ?? 'ملاحظة'),
            'evidence' => (string) ($finding['evidence'] ?? ''),
            'recommendation' => (string) ($finding['recommendation'] ?? ''),
            'source_url' => $finding['source_url'] ?? null,
            'meta_json' => $finding['meta'] ?? [],
        ]);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function persistContact(AuditRun $auditRun, ?AuditTarget $target, Workspace $workspace, Project $project, array $contact): void
    {
        OfficialContact::query()->create([
            'audit_run_id' => $auditRun->id,
            'audit_target_id' => $target?->id,
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'contact_type' => (string) ($contact['contact_type'] ?? 'official_email'),
            'contact_value' => (string) ($contact['contact_value'] ?? ''),
            'source_url' => $contact['source_url'] ?? null,
            'is_verified' => (bool) ($contact['is_verified'] ?? false),
            'is_primary' => (bool) ($contact['is_primary'] ?? false),
            'meta_json' => $contact['meta'] ?? [],
        ]);
    }

    /**
     * @param  array<string, int>  $scores
     */
    private function persistTargetScorecards(
        AuditRun $auditRun,
        ?AuditTarget $target,
        Workspace $workspace,
        Project $project,
        array $scores,
        string $scope,
    ): void {
        $labels = [
            'executive' => 'Executive Score',
            'website' => 'Website',
            'social' => 'Social',
            'seo' => 'SEO',
            'trust' => 'Trust',
            'conversion' => 'Conversion',
            'ads_readiness' => 'Ads Readiness',
            'ai_visibility' => 'AI Visibility',
            'competition' => 'Competition',
            'lead_readiness' => 'Lead Readiness',
        ];

        foreach ($scores as $code => $score) {
            Scorecard::query()->updateOrCreate(
                [
                    'audit_run_id' => $auditRun->id,
                    'audit_target_id' => $target?->id,
                    'scope' => $scope,
                    'code' => $code,
                ],
                [
                    'workspace_id' => $workspace->id,
                    'project_id' => $project->id,
                    'label' => $labels[$code] ?? $code,
                    'score' => max(0, min(100, (int) $score)),
                    'meta_json' => [],
                ],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function monitoringTrend(Project $project): array
    {
        return MonitorSnapshot::query()
            ->where('project_id', $project->id)
            ->latest('captured_at')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn (MonitorSnapshot $snapshot): array => [
                'captured_at' => $snapshot->captured_at?->toDateString(),
                'executive_score' => $snapshot->executive_score,
                'website_score' => $snapshot->website_score,
                'social_score' => $snapshot->social_score,
                'conversion_score' => $snapshot->conversion_score,
            ])
            ->values()
            ->all();
    }
}
