<?php

namespace App\Support\AI;

use App\Domain\AI\Knowledge\KnowledgePromptContext;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\Approval\Models\Approval;
use App\Domain\Comment\Models\Comment;
use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Intelligence\Models\OfficialContact;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Tooling\ProjectActionAdvisor;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Support\Str;

class WorkspaceGenerationContextBuilder
{
    private readonly ProjectMarketingBriefStore $projectMarketingBriefStore;

    private readonly StudioAnalyticalDossierBuilder $dossierBuilder;

    private readonly ProjectActionAdvisor $projectActionAdvisor;

    private readonly KnowledgePromptContext $knowledgePromptContext;

    public function __construct(
        private readonly WorkspaceProfileStore $profileStore,
        private readonly WorkspaceJourneyStore $journeyStore,
        mixed $projectMarketingBriefStore = null,
        mixed $dossierBuilder = null,
        ?ProjectActionAdvisor $projectActionAdvisor = null,
        ?KnowledgePromptContext $knowledgePromptContext = null,
    ) {
        if ($projectMarketingBriefStore instanceof StudioAnalyticalDossierBuilder && $dossierBuilder === null) {
            $dossierBuilder = $projectMarketingBriefStore;
            $projectMarketingBriefStore = null;
        }

        $this->projectMarketingBriefStore = $projectMarketingBriefStore instanceof ProjectMarketingBriefStore
            ? $projectMarketingBriefStore
            : app(ProjectMarketingBriefStore::class);
        $this->dossierBuilder = $dossierBuilder instanceof StudioAnalyticalDossierBuilder
            ? $dossierBuilder
            : app(StudioAnalyticalDossierBuilder::class);
        $this->projectActionAdvisor = $projectActionAdvisor ?? app(ProjectActionAdvisor::class);
        $this->knowledgePromptContext = $knowledgePromptContext ?? app(KnowledgePromptContext::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildForIds(
        ?int $workspaceId,
        ?int $projectId = null,
        ?string $knowledgeQuery = null,
        bool $allowAiDossier = true,
    ): array
    {
        if (! $workspaceId) {
            return $this->emptyContext();
        }

        $workspace = Workspace::query()->find($workspaceId);
        if (! $workspace) {
            return $this->emptyContext();
        }

        $project = $projectId
            ? Project::query()
                ->where('workspace_id', $workspace->id)
                ->with('client')
                ->find($projectId)
            : null;

        return $this->build($workspace, $project, $knowledgeQuery, $allowAiDossier);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(
        Workspace $workspace,
        ?Project $project = null,
        ?string $knowledgeQuery = null,
        bool $allowAiDossier = true,
    ): array
    {
        if ($project) {
            $project->loadMissing('client');
        }

        $profile = $this->profileStore->get($workspace);
        $journeySnapshot = $project ? $this->journeyStore->getSnapshot($workspace, $project) : [];
        $readiness = $project ? $this->journeyStore->getReadiness($workspace, $project) : [];
        $projectBrief = $project ? $this->projectMarketingBriefStore->get($workspace, $project) : [];
        $projectBriefAssessment = $project ? $this->projectMarketingBriefStore->assess($projectBrief) : [];
        $latestAudit = $project ? $this->latestAudit($workspace, $project) : null;
        $officialContacts = $project ? $this->officialContacts($latestAudit) : [];

        $toolSummaries = $this->toolSummaryRows($workspace, $project);
        $toolContexts = $this->toolContextRows($workspace, $project);
        $toolRuns = $this->toolRuns($workspace, $project);
        $approvals = $this->approvalNotes($workspace, $project);
        $clientNotes = $this->clientNotes($project);
        $comments = $this->commentNotes(
            $workspace,
            $project,
            array_column($toolRuns, 'id'),
            $this->generationIds($workspace, $project),
        );

        $context = [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'type' => $workspace->type,
            ],
            'project' => $project ? [
                'id' => $project->id,
                'name' => $project->name,
                'stage' => $project->stage,
                'status' => $project->status,
                'sector' => $this->stringValue($project->sector),
                'market_country' => $this->stringValue($project->market_country),
                'website' => $this->stringValue($project->primary_domain),
                'social_links' => $this->projectSocialLinks($project),
                'competitors' => $this->stringList($project->competitors_json ?? []),
            ] : null,
            'client' => $project?->client ? [
                'id' => $project->client->id,
                'name' => $project->client->name,
            ] : null,
            'workspace_profile' => $profile,
            'project_brief' => $projectBrief,
            'project_brief_assessment' => $projectBriefAssessment,
            'project_next_action' => $project
                ? $this->projectActionAdvisor->advise($project, $projectBrief, $projectBriefAssessment, $journeySnapshot, $toolSummaries)
                : [],
            'latest_audit_report' => $latestAudit?->report_json ?? [],
            'latest_audit_summary' => $latestAudit?->summary_json ?? [],
            'official_contacts' => $officialContacts,
            'journey_snapshot' => $journeySnapshot,
            'readiness_snapshot' => $readiness,
            'tool_summaries' => $toolSummaries,
            'tool_contexts' => $toolContexts,
            'tool_runs' => $toolRuns,
            'client_notes' => $clientNotes,
            'approval_notes' => $approvals,
            'comment_notes' => $comments,
            'knowledge_evidence' => [],
            'knowledge_evidence_prompt' => '',
        ];

        if ($project && (bool) config('services.knowledge.retrieval', false)) {
            $knowledge = $this->knowledgePromptContext->forProject(
                $project,
                is_string($knowledgeQuery) && trim($knowledgeQuery) !== ''
                    ? trim($knowledgeQuery)
                    : $this->knowledgeQuery($project, $projectBrief),
            );
            $context['knowledge_evidence'] = $knowledge['evidence'];
            $context['knowledge_evidence_prompt'] = $knowledge['prompt_block'];
        }

        $context['analytical_dossier'] = $this->dossierBuilder->build(
            $workspace,
            $project,
            $context,
            $allowAiDossier,
        );

        $context['prompt_block'] = $this->buildPromptBlock($context);

        return $context;
    }

    public function promptBlockForIds(
        ?int $workspaceId,
        ?int $projectId = null,
        ?string $knowledgeQuery = null,
        bool $allowAiDossier = true,
    ): string
    {
        return $this->buildForIds(
            $workspaceId,
            $projectId,
            $knowledgeQuery,
            $allowAiDossier,
        )['prompt_block'] ?? '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function buildPromptBlock(array $context): string
    {
        $parts = [
            '=== السياق الموحد المعتمد قبل أي توليد ===',
        ];

        $dossierGuide = $this->stringValue($context['analytical_dossier']['guide_markdown'] ?? null);
        if ($dossierGuide !== '') {
            $parts[] = '=== الملف التحليلي المرجعي الإلزامي ===';
            $parts[] = $dossierGuide;
        }

        $knowledgeEvidence = $this->stringValue($context['knowledge_evidence_prompt'] ?? null);
        if ($knowledgeEvidence !== '') {
            $parts[] = $knowledgeEvidence;
        }

        $parts[] = 'المساحة: '.$this->stringValue($context['workspace']['name'] ?? null);

        if (is_array($context['project'] ?? null)) {
            $parts[] = 'المشروع: '.$this->stringValue($context['project']['name'] ?? null);
        }

        if (is_array($context['client'] ?? null)) {
            $parts[] = 'العميل: '.$this->stringValue($context['client']['name'] ?? null);
        }

        if (is_array($context['project'] ?? null)) {
            $presence = array_filter([
                ($context['project']['website'] ?? '') !== '' ? 'الموقع الإلكتروني: '.$context['project']['website'] : null,
                ! empty($context['project']['social_links']) ? 'حسابات التواصل: '.$this->implodeList($context['project']['social_links'], ' | ') : null,
                ($context['project']['sector'] ?? '') !== '' ? 'القطاع: '.$context['project']['sector'] : null,
                ($context['project']['market_country'] ?? '') !== '' ? 'السوق/الدولة: '.$context['project']['market_country'] : null,
                ! empty($context['project']['competitors']) ? 'المنافسون: '.$this->implodeList($context['project']['competitors'], ' | ') : null,
            ]);

            if ($presence !== []) {
                $parts[] = '=== الحضور الرقمي والسوق (من ملف المشروع) ===';
                foreach ($presence as $line) {
                    $parts[] = '- '.$line;
                }
                $parts[] = 'ملاحظة: هذه روابط معلنة من المستخدم. استند إليها في التحليل والتوصيات؛ إن احتجت محتوى الصفحة نفسه فاطلب من المستخدم لصقه بدل افتراض تعذّر الوصول.';
            }
        }

        $profileLine = $this->formatKeyValueLine('ملف المساحة', [
            'persona' => $context['workspace_profile']['persona'] ?? null,
            'goal' => $context['workspace_profile']['primary_goal'] ?? null,
            'audience' => $context['workspace_profile']['audience'] ?? null,
            'country' => $context['workspace_profile']['country'] ?? null,
            'content_locale' => $context['workspace_profile']['content_locale'] ?? null,
        ]);
        if ($profileLine !== null) {
            $parts[] = $profileLine;
        }

        if (! empty($context['project_brief_assessment']['reports']['executive_brief'])) {
            $parts[] = '=== ملف المشروع التسويقي ===';
            foreach ($context['project_brief_assessment']['reports']['executive_brief'] as $line) {
                $parts[] = '- '.$line;
            }
        }

        if (! empty($context['project_brief_assessment']['reports']['audience_snapshot'])) {
            $parts[] = '=== صورة الجمهور ===';
            foreach ($context['project_brief_assessment']['reports']['audience_snapshot'] as $line) {
                $parts[] = '- '.$line;
            }
        }

        if (! empty($context['project_brief_assessment']['reports']['offer_positioning'])) {
            $parts[] = '=== العرض والتمركز ===';
            foreach ($context['project_brief_assessment']['reports']['offer_positioning'] as $line) {
                $parts[] = '- '.$line;
            }
        }

        if (! empty($context['project_next_action']['headline'])) {
            $parts[] = '=== القرار التالي للمشروع ===';
            $parts[] = '- '.$this->stringValue($context['project_next_action']['headline'] ?? null);
            if ($this->stringValue($context['project_next_action']['reason'] ?? null) !== '') {
                $parts[] = '- السبب: '.$this->stringValue($context['project_next_action']['reason'] ?? null);
            }
        }

        if (! empty($context['latest_audit_report']['executive_scores'])) {
            $parts[] = '=== ملخص تحليل مشروعك ===';
            foreach (($context['latest_audit_report']['executive_scores'] ?? []) as $label => $score) {
                if (is_numeric($score)) {
                    $parts[] = '- '.$label.': '.$score.'/100';
                }
            }
        }

        if (! empty($context['latest_audit_report']['honest_diagnosis'])) {
            $parts[] = '=== Honest Diagnosis ===';
            foreach ($context['latest_audit_report']['honest_diagnosis'] as $line) {
                $parts[] = '- '.$line;
            }
        }

        if (! empty($context['official_contacts'])) {
            $parts[] = '=== Official Contacts ===';
            foreach ($context['official_contacts'] as $contact) {
                $parts[] = '- '.implode(' | ', array_filter([
                    $contact['contact_type'] ?? null,
                    $contact['contact_value'] ?? null,
                ]));
            }
        }

        $journeyLine = $this->formatKeyValueLine('الرحلة الحالية', [
            'current_stage' => $context['journey_snapshot']['current_stage'] ?? null,
            'current_step' => $context['journey_snapshot']['current_step'] ?? null,
        ]);
        if ($journeyLine !== null) {
            $parts[] = $journeyLine;
        }

        if (! empty($context['readiness_snapshot']) && is_array($context['readiness_snapshot'])) {
            $parts[] = 'جاهزية المشروع: '.$this->readinessLine($context['readiness_snapshot']);
        }

        $toolSummaryLines = collect($context['tool_summaries'] ?? [])
            ->map(fn (array $summary): string => '- '.$summary['tool_code'].': '.implode(' | ', array_filter([
                $summary['headline'] ?? null,
                $summary['text'] ?? null,
                $this->implodeList($summary['bullets'] ?? [], ' / '),
            ])))
            ->filter()
            ->values()
            ->all();

        if ($toolSummaryLines !== []) {
            $parts[] = '=== ملخصات الأدوات ===';
            $parts = [...$parts, ...$toolSummaryLines];
        }

        $toolRunLines = collect($context['tool_runs'] ?? [])
            ->flatMap(function (array $run): array {
                $lines = [];
                $header = implode(' | ', array_filter([
                    $run['tool_code'] ?? null,
                    $run['mode'] ?? null,
                    $run['headline'] ?? null,
                    $run['created_at'] ?? null,
                ]));

                if ($header !== '') {
                    $lines[] = '- تشغيل أداة: '.$header;
                }

                if (! empty($run['inputs'])) {
                    $lines[] = '  المدخلات: '.$this->formatAssociativeList($run['inputs']);
                }

                if (! empty($run['insights'])) {
                    $lines[] = '  الخلاصة التنفيذية: '.$this->implodeList($run['insights'], ' / ');
                }

                if (! empty($run['next_actions'])) {
                    $lines[] = '  الخطوات التالية: '.$this->implodeList($run['next_actions'], ' / ');
                }

                return $lines;
            })
            ->values()
            ->all();

        if ($toolRunLines !== []) {
            $parts[] = '=== تشغيلات الأدوات التفصيلية ===';
            $parts = [...$parts, ...$toolRunLines];
        }

        $toolContextLines = collect($context['tool_contexts'] ?? [])
            ->map(function (array $toolContext): ?string {
                $line = implode(' | ', array_filter([
                    $toolContext['tool_code'] ?? null,
                    $toolContext['project_summary'] ?? null,
                    $toolContext['client_summary'] ?? null,
                    $toolContext['tool_blueprint_summary'] ?? null,
                ]));

                return $line !== '' ? '- '.$line : null;
            })
            ->filter()
            ->values()
            ->all();

        if ($toolContextLines !== []) {
            $parts[] = '=== السياق المحفوظ للأدوات ===';
            $parts = [...$parts, ...$toolContextLines];
        }

        if (! empty($context['client_notes'])) {
            $parts[] = '=== ملاحظات العميل ===';
            foreach ($context['client_notes'] as $note) {
                $parts[] = '- '.$note;
            }
        }

        if (! empty($context['approval_notes'])) {
            $parts[] = '=== ملاحظات الاعتماد والمراجعة ===';
            foreach ($context['approval_notes'] as $approval) {
                $parts[] = '- '.implode(' | ', array_filter([
                    $approval['item_type'] ?? null,
                    $approval['status'] ?? null,
                    $approval['note'] ?? null,
                ]));
            }
        }

        if (! empty($context['comment_notes'])) {
            $parts[] = '=== التعليقات والملاحظات المرتبطة ===';
            foreach ($context['comment_notes'] as $comment) {
                $parts[] = '- '.implode(' | ', array_filter([
                    $comment['entity'] ?? null,
                    $comment['body'] ?? null,
                ]));
            }
        }

        return implode("\n", array_filter($parts));
    }

    /** @param array<string, mixed> $projectBrief */
    private function knowledgeQuery(Project $project, array $projectBrief): string
    {
        $values = [
            $project->name,
            $project->sector,
            $project->market_country,
            $project->primary_domain,
        ];

        array_walk_recursive($projectBrief, function (mixed $value) use (&$values): void {
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        });

        return Str::limit(implode(' ', array_unique(array_filter($values))), 1200, '');
    }

    /**
     * @return array<int, array{tool_code: string, headline: string, text: string, bullets: array<int, string>}>
     */
    private function toolSummaryRows(Workspace $workspace, ?Project $project): array
    {
        return WorkspaceData::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project?->id)
            ->where('key', 'like', 'tool.summary.%')
            ->orderBy('updated_at')
            ->get()
            ->map(function (WorkspaceData $row): array {
                $payload = is_array($row->value_json) ? $row->value_json : [];

                return [
                    'tool_code' => str_replace('tool.summary.', '', $row->key),
                    'headline' => $this->limit($payload['headline'] ?? null),
                    'text' => $this->limit($payload['text'] ?? null, 320),
                    'bullets' => $this->stringList($payload['bullets'] ?? []),
                ];
            })
            ->filter(fn (array $row): bool => $row['headline'] !== '' || $row['text'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{tool_code: string, project_summary: string, client_summary: string, tool_blueprint_summary: string}>
     */
    private function toolContextRows(Workspace $workspace, ?Project $project): array
    {
        return WorkspaceData::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project?->id)
            ->where('key', 'like', 'tool.context.%')
            ->orderBy('updated_at')
            ->get()
            ->map(function (WorkspaceData $row): array {
                $payload = is_array($row->value_json) ? $row->value_json : [];

                return [
                    'tool_code' => str_replace('tool.context.', '', $row->key),
                    'project_summary' => $this->formatKeyValueSummary($payload['project'] ?? []),
                    'client_summary' => $this->formatKeyValueSummary($payload['client'] ?? []),
                    'tool_blueprint_summary' => $this->formatKeyValueSummary($payload['tool_blueprint'] ?? []),
                ];
            })
            ->filter(fn (array $row): bool => $row['project_summary'] !== '' || $row['client_summary'] !== '' || $row['tool_blueprint_summary'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, tool_code: string, mode: string, headline: string, created_at: string, inputs: array<string, string>, insights: array<int, string>, next_actions: array<int, string>}>
     */
    private function toolRuns(Workspace $workspace, ?Project $project): array
    {
        if (! $project) {
            return [];
        }

        // تقليم: نكتفي بأحدث 15 تشغيلاً بتفاصيلها الكاملة (المدخلات ثقيلة التوكنات)؛
        // بينما تُغطّي «ملخصات الأدوات» كل الأدوات المنجَزة بإيجاز — فلا تضيع أي أداة.
        return ToolRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->latest()
            ->limit(15)
            ->get()
            ->map(function (ToolRun $run): array {
                $summary = is_array($run->summary_json) ? $run->summary_json : [];
                $output = is_array($run->output_json) ? $run->output_json : [];

                return [
                    'id' => $run->id,
                    'tool_code' => $run->tool_code,
                    'mode' => $run->mode,
                    'headline' => $this->limit($summary['headline'] ?? $output['headline'] ?? null),
                    'created_at' => $run->created_at?->toDateTimeString() ?? '',
                    'inputs' => $this->scalarFields($run->inputs_json),
                    'insights' => $this->insightList($summary, $output),
                    'next_actions' => $this->stringList($run->next_actions_json),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{item_type: string, status: string, note: string}>
     */
    private function approvalNotes(Workspace $workspace, ?Project $project): array
    {
        if (! $project) {
            return [];
        }

        return Approval::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->latest()
            ->get()
            ->map(fn (Approval $approval): array => [
                'item_type' => $approval->item_type,
                'status' => $approval->status,
                'note' => $this->limit($approval->note, 400),
            ])
            ->filter(fn (array $approval): bool => $approval['note'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function clientNotes(?Project $project): array
    {
        $notes = $project?->client?->contact_info['notes'] ?? null;

        if (! is_string($notes) || trim($notes) === '') {
            return [];
        }

        return preg_split('/\r\n|\r|\n/', trim($notes)) ?: [];
    }

    /**
     * @param  array<int, int>  $toolRunIds
     * @param  array<int, int>  $generationIds
     * @return array<int, array{entity: string, body: string}>
     */
    private function commentNotes(
        Workspace $workspace,
        ?Project $project,
        array $toolRunIds,
        array $generationIds,
    ): array {
        $query = Comment::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($commentQuery) use ($workspace, $project, $toolRunIds, $generationIds): void {
                $commentQuery->where(function ($workspaceComment) use ($workspace): void {
                    $workspaceComment
                        ->where('entity_type', 'workspace')
                        ->where('entity_id', $workspace->id);
                });

                if ($project) {
                    $commentQuery->orWhere(function ($projectComment) use ($project): void {
                        $projectComment
                            ->where('entity_type', 'project')
                            ->where('entity_id', $project->id);
                    });

                    if ($project->client_id) {
                        $commentQuery->orWhere(function ($clientComment) use ($project): void {
                            $clientComment
                                ->where('entity_type', 'client')
                                ->where('entity_id', $project->client_id);
                        });
                    }
                }

                if ($toolRunIds !== []) {
                    $commentQuery->orWhere(function ($toolRunComment) use ($toolRunIds): void {
                        $toolRunComment
                            ->where('entity_type', 'tool_run')
                            ->whereIn('entity_id', $toolRunIds);
                    });
                }

                if ($generationIds !== []) {
                    $commentQuery->orWhere(function ($generationComment) use ($generationIds): void {
                        $generationComment
                            ->where('entity_type', 'ai_generation')
                            ->whereIn('entity_id', $generationIds);
                    });
                }
            });

        return $query
            ->latest()
            ->get()
            ->map(fn (Comment $comment): array => [
                'entity' => $comment->entity_type.'#'.$comment->entity_id,
                'body' => $this->limit($comment->body, 400),
            ])
            ->filter(fn (array $comment): bool => $comment['body'] !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function generationIds(Workspace $workspace, ?Project $project): array
    {
        if (! $project) {
            return [];
        }

        return AIGeneration::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->pluck('id')
            ->all();
    }

    /**
     * Flattens the project's declared + verified social profiles into concise strings.
     *
     * @return array<int, string>
     */
    private function projectSocialLinks(Project $project): array
    {
        $links = [];

        foreach ((array) ($project->official_social_links_json ?? []) as $key => $value) {
            if (is_string($value) && trim($value) !== '') {
                $links[] = is_string($key) ? $key.': '.trim($value) : trim($value);
            }
        }

        foreach ((array) ($project->verified_social_profiles_json ?? []) as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $label = trim(implode(' ', array_filter([
                $this->stringValue($profile['network'] ?? null),
                $this->stringValue($profile['handle'] ?? null),
            ])));
            $url = $this->stringValue($profile['url'] ?? null);
            $entry = trim($label.($url !== '' ? ' ('.$url.')' : ''));

            if ($entry !== '') {
                $links[] = $entry;
            }
        }

        return collect($links)
            ->map(fn (string $line): string => $this->limit($line, 160))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function limit(mixed $value, int $length = 220): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim(Str::limit(preg_replace('/\s+/', ' ', trim($value)) ?? '', $length, '...'));
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn (mixed $value): string => $this->limit($value, 220))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function scalarFields(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->mapWithKeys(function (mixed $value, mixed $key): array {
                if (! is_string($key)) {
                    return [];
                }

                if (is_string($value)) {
                    $normalized = $this->limit($value, 160);

                    return $normalized !== '' ? [$key => $normalized] : [];
                }

                if (is_array($value)) {
                    $normalized = $this->implodeList($this->stringList($value), ' / ');

                    return $normalized !== '' ? [$key => $normalized] : [];
                }

                if (is_numeric($value) || is_bool($value)) {
                    return [$key => (string) $value];
                }

                return [];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $output
     * @return array<int, string>
     */
    private function insightList(array $summary, array $output): array
    {
        $bullets = $this->stringList($summary['bullets'] ?? []);
        $outputInsights = $this->stringList($output['insights'] ?? []);
        $outputSummary = $this->limit($output['summary'] ?? null, 260);

        return collect([
            ...$bullets,
            ...$outputInsights,
            $outputSummary,
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function formatKeyValueSummary(mixed $values): string
    {
        if (! is_array($values)) {
            return '';
        }

        return $this->formatAssociativeList($this->scalarFields($values));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function formatKeyValueLine(string $label, array $values): ?string
    {
        $summary = $this->formatAssociativeList($this->scalarFields($values));

        return $summary !== '' ? $label.': '.$summary : null;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function formatAssociativeList(array $values): string
    {
        return collect($values)
            ->map(fn (string $value, string $key): string => $key.': '.$value)
            ->implode(' | ');
    }

    /**
     * @param  array<int, mixed>  $readiness
     */
    private function readinessLine(array $readiness): string
    {
        return collect($readiness)
            ->map(function (mixed $dimension): ?string {
                if (! is_array($dimension)) {
                    return null;
                }

                $label = $this->stringValue($dimension['label'] ?? null);
                $score = $dimension['score'] ?? null;

                if ($label === '' || ! is_numeric($score)) {
                    return null;
                }

                return $label.': '.$score.'%';
            })
            ->filter()
            ->implode(' | ');
    }

    /**
     * @param  array<int, string>  $values
     */
    private function implodeList(array $values, string $separator = ' | '): string
    {
        return collect($values)
            ->filter()
            ->implode($separator);
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContext(): array
    {
        return [
            'workspace' => null,
            'project' => null,
            'client' => null,
            'workspace_profile' => [],
            'project_brief' => [],
            'project_brief_assessment' => [],
            'project_next_action' => [],
            'latest_audit_report' => [],
            'latest_audit_summary' => [],
            'official_contacts' => [],
            'journey_snapshot' => [],
            'readiness_snapshot' => [],
            'tool_summaries' => [],
            'tool_contexts' => [],
            'tool_runs' => [],
            'client_notes' => [],
            'approval_notes' => [],
            'comment_notes' => [],
            'analytical_dossier' => [],
            'prompt_block' => '',
        ];
    }

    private function latestAudit(Workspace $workspace, Project $project): ?AuditRun
    {
        return AuditRun::query()
            ->where('workspace_id', $workspace->id)
            ->where('project_id', $project->id)
            ->latest()
            ->first();
    }

    /**
     * @return array<int, array{contact_type: string, contact_value: string}>
     */
    private function officialContacts(?AuditRun $auditRun): array
    {
        if (! $auditRun) {
            return [];
        }

        return OfficialContact::query()
            ->where('audit_run_id', $auditRun->id)
            ->orderByDesc('is_primary')
            ->get()
            ->map(fn (OfficialContact $contact): array => [
                'contact_type' => $contact->contact_type,
                'contact_value' => $contact->contact_value,
            ])
            ->values()
            ->all();
    }
}
