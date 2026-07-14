<?php

namespace App\Application\Interview;

use App\Application\Intelligence\CompileWorkspaceIntelligenceAction;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use App\Models\User;
use App\Support\Interview\FounderInterviewCatalog;

/**
 * مقابلة المؤسِّس (المرحلة 4): تحفظ إجابات المؤسِّس كقيم معيارية (canonical) في
 * workspace_data بمصدر user_confirmed، فتقرأها الأدوات عبر الملء المسبق (المرحلة 1).
 *
 * حتمية بالكامل — لا تعتمد على LLM: إجابات المستخدم حقائق تُحفظ كما هي. هذا يُغلق
 * الحلقة: مقابلة واحدة تملأ أساس عدّة أدوات دفعة واحدة (Context Capture §21).
 */
class RunFounderInterviewAction
{
    public function __construct(
        private readonly CompileWorkspaceIntelligenceAction $compileIntelligence,
    ) {}

    /**
     * @param  array<string, mixed>  $answers  canonicalKey => نص الإجابة
     * @return array{saved: array<int, string>, count: int}
     */
    public function handle(Workspace $workspace, Project $project, User $actor, array $answers): array
    {
        $allowed = array_flip(FounderInterviewCatalog::keys());
        $saved = [];

        foreach ($answers as $key => $value) {
            if (! is_string($key) || ! isset($allowed[$key]) || ! is_string($value)) {
                continue;
            }

            $value = trim($value);
            if ($value === '') {
                continue;
            }

            WorkspaceData::query()->updateOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'project_id' => $project->id,
                    'key' => $key,
                ],
                [
                    'value_json' => [
                        'value' => $value,
                        'source_tool' => 'founder-interview',
                        'provenance' => 'user_confirmed',
                        'updated_at' => now()->toDateTimeString(),
                    ],
                ],
            );

            $saved[] = $key;
        }

        if ($saved !== []) {
            // نُحدّث الذكاء المخزّن بعد إثراء الأساس (كما تفعل RunToolAction).
            $this->compileIntelligence->handle($workspace, $project);
            $this->compileIntelligence->handle($workspace);
        }

        return ['saved' => $saved, 'count' => count($saved)];
    }
}
