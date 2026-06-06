<?php

namespace App\Support\Projects;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Support\Dashboard\GoalCatalog;
use App\Support\Workspaces\WorkspaceProfileStore;

class ProjectMarketingBriefStore
{
    public const BRIEF_KEY = 'project.marketing_brief';

    public function __construct(
        private readonly WorkspaceProfileStore $profileStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function get(Workspace $workspace, Project $project): array
    {
        return $this->getMany($workspace, [$project])[$project->id] ?? $this->normalize([]);
    }

    /**
     * @param  iterable<int, Project>  $projects
     * @return array<int, array<string, mixed>>
     */
    public function getMany(Workspace $workspace, iterable $projects): array
    {
        $projectIds = collect($projects)
            ->map(fn (Project $project): int => $project->id)
            ->filter()
            ->values();

        if ($projectIds->isEmpty()) {
            return [];
        }

        $rows = WorkspaceData::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('project_id', $projectIds->all())
            ->where('key', self::BRIEF_KEY)
            ->get()
            ->keyBy('project_id');

        return $projectIds
            ->mapWithKeys(function (int $projectId) use ($rows): array {
                $payload = is_array($rows->get($projectId)?->value_json) ? $rows->get($projectId)->value_json : [];

                return [$projectId => $this->normalize($payload)];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function put(Workspace $workspace, Project $project, array $attributes): WorkspaceData
    {
        $current = $this->get($workspace, $project);
        $merged = array_replace_recursive($current, $this->normalize($attributes));
        $assessment = $this->assess($merged);

        $payload = [
            ...$merged,
            '_meta' => [
                'completeness_score' => $assessment['completeness_score'],
                'known_fields' => $assessment['known_fields'],
                'total_fields' => $assessment['total_fields'],
                'updated_at' => now()->toDateTimeString(),
            ],
        ];

        $saved = WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'key' => self::BRIEF_KEY,
            ],
            [
                'value_json' => $payload,
            ],
        );

        $this->syncWorkspaceProfile($workspace, $merged);

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    public function assess(array $brief): array
    {
        $fields = [
            'business.summary' => data_get($brief, 'business.summary'),
            'business.offer' => data_get($brief, 'business.offer'),
            'audience.ideal_customer' => data_get($brief, 'audience.ideal_customer'),
            'audience.pain_points' => data_get($brief, 'audience.pain_points'),
            'goals.primary_goal' => data_get($brief, 'goals.primary_goal'),
            'goals.success_metric' => data_get($brief, 'goals.success_metric'),
            'current_marketing.channels' => data_get($brief, 'current_marketing.channels'),
            'current_marketing.current_state' => data_get($brief, 'current_marketing.current_state'),
            'brand.voice' => data_get($brief, 'brand.voice'),
            'positioning.edge' => data_get($brief, 'positioning.edge'),
            'competition.competitors' => data_get($brief, 'competition.competitors'),
            'execution.priority' => data_get($brief, 'execution.priority'),
            'execution.next_asset' => data_get($brief, 'execution.next_asset'),
            'commercial.budget_range' => data_get($brief, 'commercial.budget_range'),
        ];

        $known = 0;
        $missingFields = [];

        foreach ($fields as $path => $value) {
            if ($this->filled($value)) {
                $known++;
                continue;
            }

            $missingFields[] = $path;
        }

        $score = (int) round(($known / max(count($fields), 1)) * 100);

        $reports = [
            'executive_brief' => $this->executiveBrief($brief),
            'audience_snapshot' => $this->audienceSnapshot($brief),
            'offer_positioning' => $this->offerPositioning($brief),
            'channel_direction' => $this->channelDirection($brief),
            'decision_summary' => $this->decisionSummary($brief, $missingFields),
        ];

        return [
            'completeness_score' => $score,
            'known_fields' => $known,
            'total_fields' => count($fields),
            'missing_fields' => $missingFields,
            'missing_labels' => array_map([$this, 'humanizePath'], $missingFields),
            'reports' => $reports,
            'next_actions' => $this->nextActions($brief, $missingFields),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        return [
            'business' => [
                'summary' => trim((string) data_get($payload, 'business.summary', '')),
                'offer' => trim((string) data_get($payload, 'business.offer', '')),
                'market' => trim((string) data_get($payload, 'business.market', '')),
            ],
            'audience' => [
                'ideal_customer' => trim((string) data_get($payload, 'audience.ideal_customer', '')),
                'pain_points' => trim((string) data_get($payload, 'audience.pain_points', '')),
                'buying_trigger' => trim((string) data_get($payload, 'audience.buying_trigger', '')),
            ],
            'goals' => [
                'primary_goal' => trim((string) data_get($payload, 'goals.primary_goal', '')),
                'success_metric' => trim((string) data_get($payload, 'goals.success_metric', '')),
                'timeframe' => trim((string) data_get($payload, 'goals.timeframe', '')),
            ],
            'current_marketing' => [
                'channels' => trim((string) data_get($payload, 'current_marketing.channels', '')),
                'current_state' => trim((string) data_get($payload, 'current_marketing.current_state', '')),
                'assets' => trim((string) data_get($payload, 'current_marketing.assets', '')),
            ],
            'brand' => [
                'voice' => trim((string) data_get($payload, 'brand.voice', '')),
                'tone_rules' => trim((string) data_get($payload, 'brand.tone_rules', '')),
            ],
            'positioning' => [
                'edge' => trim((string) data_get($payload, 'positioning.edge', '')),
                'promise' => trim((string) data_get($payload, 'positioning.promise', '')),
            ],
            'competition' => [
                'competitors' => trim((string) data_get($payload, 'competition.competitors', '')),
                'gap' => trim((string) data_get($payload, 'competition.gap', '')),
            ],
            'execution' => [
                'priority' => trim((string) data_get($payload, 'execution.priority', '')),
                'next_asset' => trim((string) data_get($payload, 'execution.next_asset', '')),
                'delivery_notes' => trim((string) data_get($payload, 'execution.delivery_notes', '')),
            ],
            'commercial' => [
                'budget_range' => trim((string) data_get($payload, 'commercial.budget_range', '')),
                'decision_maker' => trim((string) data_get($payload, 'commercial.decision_maker', '')),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $brief
     * @return list<string>
     */
    private function executiveBrief(array $brief): array
    {
        return array_values(array_filter([
            data_get($brief, 'business.summary') !== '' ? 'النشاط: '.data_get($brief, 'business.summary') : null,
            data_get($brief, 'goals.primary_goal') !== '' ? 'الهدف: '.data_get($brief, 'goals.primary_goal') : null,
            data_get($brief, 'execution.priority') !== '' ? 'الأولوية الحالية: '.data_get($brief, 'execution.priority') : null,
            data_get($brief, 'commercial.budget_range') !== '' ? 'نطاق الميزانية: '.data_get($brief, 'commercial.budget_range') : null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $brief
     * @return list<string>
     */
    private function audienceSnapshot(array $brief): array
    {
        return array_values(array_filter([
            data_get($brief, 'audience.ideal_customer') ?: null,
            data_get($brief, 'audience.pain_points') ?: null,
            data_get($brief, 'audience.buying_trigger') ?: null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $brief
     * @return list<string>
     */
    private function offerPositioning(array $brief): array
    {
        return array_values(array_filter([
            data_get($brief, 'business.offer') ?: null,
            data_get($brief, 'positioning.edge') ?: null,
            data_get($brief, 'positioning.promise') ?: null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $brief
     * @return list<string>
     */
    private function channelDirection(array $brief): array
    {
        return array_values(array_filter([
            data_get($brief, 'current_marketing.channels') ?: null,
            data_get($brief, 'current_marketing.current_state') ?: null,
            data_get($brief, 'execution.next_asset') ?: null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  list<string>  $missingFields
     * @return list<string>
     */
    private function decisionSummary(array $brief, array $missingFields): array
    {
        $lines = [];

        if ($this->filled(data_get($brief, 'goals.primary_goal')) && $this->filled(data_get($brief, 'audience.ideal_customer'))) {
            $lines[] = 'لديك الآن ركيزتان تسمحان بتشخيص أوضح: الهدف والجمهور.';
        }

        if ($this->filled(data_get($brief, 'business.offer')) && $this->filled(data_get($brief, 'positioning.edge'))) {
            $lines[] = 'العرض والتميّز واضحان بما يكفي للانتقال إلى أدوات الرسائل والخطة.';
        }

        if (count($missingFields) > 0) {
            $lines[] = 'أهم ما ينقص الآن: '.$this->humanizePath($missingFields[0]).'.';
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  list<string>  $missingFields
     * @return list<string>
     */
    private function nextActions(array $brief, array $missingFields): array
    {
        $actions = [];

        if (! $this->filled(data_get($brief, 'audience.ideal_customer'))) {
            $actions[] = 'أكمل وصف العميل المثالي حتى تتحسن دقة العرض والرسائل.';
        }

        if (! $this->filled(data_get($brief, 'business.offer'))) {
            $actions[] = 'وضّح العرض الرئيسي الذي تبيعه قبل تشغيل أدوات التمركز والخطة.';
        }

        if (! $this->filled(data_get($brief, 'current_marketing.channels'))) {
            $actions[] = 'حدّد القنوات الحالية حتى تصبح توصيات الخطة والمحتوى قابلة للتنفيذ.';
        }

        if ($actions === [] && count($missingFields) > 0) {
            $actions[] = 'أغلق الفجوات المتبقية: '.implode('، ', array_map([$this, 'humanizePath'], array_slice($missingFields, 0, 3))).'.';
        }

        if ($actions === []) {
            $actions[] = 'الملف قوي بما يكفي للانتقال إلى التشخيص والعرض والخطة والاستوديو.';
        }

        return $actions;
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    private function syncWorkspaceProfile(Workspace $workspace, array $brief): void
    {
        $payload = array_filter([
            'audience' => data_get($brief, 'audience.ideal_customer'),
            'current_challenge' => data_get($brief, 'execution.priority'),
            'country' => data_get($brief, 'business.market'),
            'content_locale' => data_get($brief, 'brand.voice') !== '' ? 'ar_modern_fusha' : null,
        ], fn (mixed $value) => $this->filled($value));

        $goal = data_get($brief, 'goals.primary_goal');
        if (is_string($goal) && GoalCatalog::exists($goal)) {
            $payload['primary_goal'] = $goal;
        }

        if ($payload !== []) {
            $this->profileStore->put($workspace, $payload);
        }
    }

    private function filled(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : ! empty($value);
    }

    private function humanizePath(string $path): string
    {
        return match ($path) {
            'business.summary' => 'وصف النشاط',
            'business.offer' => 'العرض الرئيسي',
            'audience.ideal_customer' => 'العميل المثالي',
            'audience.pain_points' => 'ألم الجمهور',
            'goals.primary_goal' => 'الهدف الأساسي',
            'goals.success_metric' => 'مؤشر النجاح',
            'current_marketing.channels' => 'القنوات الحالية',
            'current_marketing.current_state' => 'وضع التسويق الحالي',
            'brand.voice' => 'صوت العلامة',
            'positioning.edge' => 'ميزة التمركز',
            'competition.competitors' => 'المنافسون',
            'execution.priority' => 'الأولوية الحالية',
            'execution.next_asset' => 'الأصل أو المخرج التالي',
            'commercial.budget_range' => 'نطاق الميزانية',
            default => $path,
        };
    }
}
