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
                'details' => [
                    'اضغط «أضف مشروع» وأدخل اسم مشروعك ونوع نشاطك.',
                    'بعد الإضافة سنحلّل وضعك ونرشّح لك أول أداة تبدأ بها.',
                ],
                'action_label' => 'أضف مشروع',
                'action_route' => route('projects.create'),
                'action_type' => 'create_project',
                'tool_code' => null,
                'project_public_id' => null,
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
                'details' => [
                    'حدّد من أنت (صاحب فكرة، مقدم خدمة، مشروع قائم...) وما هدفك الأساسي.',
                    'صف جمهورك: من هو عميلك ومدى معرفته بك.',
                    'تستغرق الأسئلة أقل من 3 دقائق، وتنعكس مباشرة على دقة كل النتائج.',
                ],
                'action_label' => 'أكمل التعريف',
                'action_route' => route('onboarding.show'),
                'action_type' => 'onboarding',
                'tool_code' => null,
                'project_public_id' => (string) $targetProject->public_id,
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
            $minutes = (int) ($tool->estimated_minutes ?? 0);
            $description = trim((string) ($tool->description ?? ''));

            return [
                'title' => 'ابدأ بـ'.$tool->name,
                'summary' => $description !== ''
                    ? $description
                    : 'هذه أنسب خطوة لمشروعك الآن. أنجزها وننتقل للتي بعدها.',
                'details' => array_values(array_filter([
                    'أنت الآن في مرحلة «'.$this->stageLabel($currentStage).'» — وهذه الأداة هي خطوتها التالية.',
                    'افتح الأداة وأجب عن أسئلتها من واقع مشروعك؛ لا تحتاج تجهيزاً مسبقاً.',
                    $minutes > 0 ? 'الزمن المتوقع للإنجاز: نحو '.$minutes.' دقيقة.' : null,
                    'عند الحفظ تُستخدم نتيجتها تلقائياً في الأدوات والتقارير التالية.',
                ])),
                'action_label' => 'ابدأ الآن',
                'action_route' => route('tools.show', $tool),
                'action_type' => 'tool',
                'tool_code' => (string) $tool->code,
                'project_public_id' => (string) $targetProject->public_id,
                'stage' => $currentStage,
            ];
        }

        $nextStage = min($currentStage + 1, 5);

        return [
            'title' => 'انتقل إلى: '.$this->stageLabel($nextStage),
            'summary' => 'أنهيت هذه المرحلة. تابع إلى الخطوة التالية.',
            'details' => [
                'أكملت أدوات مرحلة «'.$this->stageLabel($currentStage).'».',
                'افتح قائمة الأدوات وابدأ بأول أداة في مرحلة «'.$this->stageLabel($nextStage).'».',
            ],
            'action_label' => 'تابع',
            'action_route' => route('tools.index'),
            'action_type' => 'tools_index',
            'tool_code' => null,
            'project_public_id' => (string) $targetProject->public_id,
            'stage' => $nextStage,
        ];
    }

    private function stageLabel(int $stage): string
    {
        return StageCatalog::label($stage);
    }
}
