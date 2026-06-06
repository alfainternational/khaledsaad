<?php

namespace App\Support\Dashboard;

use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Workspace\Models\Workspace;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;

class NextStepRecommendationService
{
    public function __construct(
        private readonly WorkspaceProfileStore $profileStore,
        private readonly WorkspaceJourneyStore $journeyStore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forWorkspace(Workspace $workspace, ?Project $project = null): array
    {
        $profile = $this->profileStore->get($workspace);
        $targetProject = $project ?? $workspace->projects()->latest('updated_at')->first();

        if (! $targetProject) {
            return [
                'title' => 'ابدأ بإضافة مشروعك الأول',
                'summary' => 'أضف مشروعك، ونبدأ نحلّله ونعطيك خطوات واضحة تمشي عليها.',
                'action_label' => 'أضف مشروع',
                'action_route' => route('projects.create'),
                'stage' => 1,
            ];
        }

        if (
            empty($profile['persona'])
            || empty($profile['primary_goal'])
            || empty($profile['audience'])
            || empty($profile['awareness_level'])
        ) {
            return [
                'title' => 'عرّفنا على مشروعك أكثر',
                'summary' => 'أجب عن أسئلة قصيرة عن مشروعك وعملائك، حتى تكون نتائجنا وخطواتنا على مقاسك أنت.',
                'action_label' => 'أكمل التعريف',
                'action_route' => route('onboarding.show'),
                'stage' => (int) $targetProject->stage,
            ];
        }

        $journeySnapshot = $this->journeyStore->getSnapshot($workspace, $targetProject);
        $currentStage = (int) ($journeySnapshot['current_stage'] ?? $targetProject->stage);
        $nextStep = $journeySnapshot['current_step'] ?? null;

        $tool = $nextStep
            ? Tool::query()->where('code', $nextStep)->first()
            : Tool::query()
                ->where('stage', $currentStage)
                ->whereIn('status', ['published', 'beta'])
                ->orderBy('sort_order')
                ->first();

        if ($tool) {
            return [
                'title' => 'ابدأ بـ'.$tool->name,
                'summary' => 'هذه أنسب خطوة لمشروعك الآن. أنجزها وننتقل للتي بعدها.',
                'action_label' => 'ابدأ الآن',
                'action_route' => route('tools.show', $tool),
                'stage' => $currentStage,
            ];
        }

        $nextStage = min($currentStage + 1, 5);

        return [
            'title' => 'انتقل إلى: '.$this->stageLabel($nextStage),
            'summary' => 'أنهيت هذه المرحلة. تابع إلى الخطوة التالية.',
            'action_label' => 'تابع',
            'action_route' => route('tools.index'),
            'stage' => $nextStage,
        ];
    }

    private function stageLabel(int $stage): string
    {
        return StageCatalog::label($stage);
    }
}
