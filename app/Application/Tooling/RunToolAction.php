<?php

namespace App\Application\Tooling;

use App\Application\Intelligence\CompileWorkspaceIntelligenceAction;
use App\Application\Workspace\RefreshJourneySnapshotAction;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Models\User;
use App\Support\Tooling\CanonicalOutputMapper;
use App\Support\Tooling\MarketingConsistencyInspector;
use App\Support\Tooling\ProjectCanonicalFacts;
use App\Support\Workspaces\WorkspaceProfileStore;

class RunToolAction
{
    public function __construct(
        private readonly BuildToolPayloadAction $buildToolPayloadAction,
        private readonly RefreshJourneySnapshotAction $refreshJourneySnapshotAction,
        private readonly CompileWorkspaceIntelligenceAction $compileIntelligence,
        private readonly CanonicalOutputMapper $canonicalOutputMapper,
        private readonly WorkspaceProfileStore $profileStore,
        private readonly MarketingConsistencyInspector $consistencyInspector,
    ) {}

    /**
     * @param  array<string, mixed>  $inputs
     */
    public function handle(
        Workspace $workspace,
        Project $project,
        Tool $tool,
        User $actor,
        string $mode = 'guided',
        array $inputs = [],
    ): ToolRun {
        $payload = $this->buildToolPayloadAction->handle($workspace, $project, $tool, $mode, $inputs);

        $run = ToolRun::query()->create([
            'workspace_id' => $workspace->id,
            'project_id' => $project->id,
            'tool_code' => $tool->code,
            'mode' => $mode,
            'inputs_json' => $inputs,
            'output_json' => $payload['output'],
            'summary_json' => $payload['summary'],
            'next_actions_json' => $payload['next_actions'],
            'source_context_json' => $payload['source_context'],
            'completeness_score' => $payload['completeness_score'],
            'created_by' => $actor->id,
        ]);

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'key' => 'tools.'.$tool->code,
            ],
            [
                'value_json' => [
                    'tool_code' => $tool->code,
                    'tool_name' => $tool->name ?: $tool->code,
                    'mode' => $mode,
                    'summary' => $payload['summary']['text'],
                    'headline' => $payload['summary']['headline'],
                    'last_run_id' => $run->id,
                    'last_run_at' => now()->toDateTimeString(),
                    'completeness_score' => $payload['completeness_score'],
                ],
            ],
        );

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'key' => 'tool.summary.'.$tool->code,
            ],
            [
                'value_json' => $payload['summary'],
            ],
        );

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'key' => 'tool.context.'.$tool->code,
            ],
            [
                'value_json' => $payload['source_context'],
            ],
        );

        // طبقة المخرجات القابلة لإعادة الاستخدام (§30): نخزّن الإجابة الفعلية بمفتاح
        // دلالي معياري (offer/tagline/ideal_customer…) لتقرأه الأدوات والاستوديو لاحقاً.
        $canonical = $this->canonicalOutputMapper->map($tool->code, $inputs);
        if ($canonical !== null) {
            WorkspaceData::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'project_id' => $project->id,
                    'key' => $canonical['key'],
                ],
                [
                    'value_json' => [
                        'value' => $canonical['value'],
                        'source_tool' => $tool->code,
                        'last_run_id' => $run->id,
                        'updated_at' => now()->toDateTimeString(),
                    ],
                ],
            );
        }

        $this->refreshJourneySnapshotAction->handle($project);

        // Compile-Ahead: نحسب الذكاء الآن (وقت الكتابة) ونخزّنه ثابتاً،
        // فيُخدَم لاحقاً كقراءة ملف بسرعة HTML بلا أي حساب وقت الطلب.
        $this->compileIntelligence->handle($workspace, $project);
        $this->compileIntelligence->handle($workspace);

        // تنبيه ما بعد الإدخال: نكشف تناقضات الحقائق (كاختلاف جمهور الملف عن
        // العميل المثالي) ونخزّنها ليعرضها الداشبورد لحظياً قبل تسرّبها للمخرجات.
        $this->recordContradictions($workspace, $project);

        return $run;
    }

    private function recordContradictions(Workspace $workspace, Project $project): void
    {
        $findings = $this->consistencyInspector->inspect(
            ProjectCanonicalFacts::for($project),
            $this->profileStore->get($workspace),
        );

        WorkspaceData::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'key' => 'intelligence.contradictions',
            ],
            [
                'value_json' => [
                    'findings' => $findings,
                    'checked_at' => now()->toDateTimeString(),
                ],
            ],
        );
    }
}
