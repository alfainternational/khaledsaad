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
                'title' => 'أنشئ أول مشروع داخل المساحة',
                'summary' => 'وجود مشروع واحد على الأقل هو نقطة البداية لكل الأدوات والتقارير والمخرجات.',
                'action_label' => 'إضافة مشروع',
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
                'title' => 'أكمل ملف العمل الأساسي',
                'summary' => 'ثبّت نوع الاستخدام ومستوى الوعي والهدف والجمهور حتى تصبح التوصيات والمخرجات مرتبطة بسياقك الحقيقي.',
                'action_label' => 'تحديث التهيئة',
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
                'title' => 'شغّل أداة '.$tool->name,
                'summary' => 'هذه هي الخطوة العملية الأقرب لحالة المشروع الحالية داخل '.$targetProject->name.' وبما يناسب هدفك ومسارك الحالي.',
                'action_label' => 'فتح الأداة المناسبة',
                'action_route' => route('tools.show', $tool),
                'stage' => $currentStage,
            ];
        }

        $nextStage = min($currentStage + 1, 5);

        return [
            'title' => 'انتقل إلى '.$this->stageLabel($nextStage),
            'summary' => 'المشروع الحالي جاهز للانتقال إلى الخطوة التالية ضمن الرحلة التسويقية الكاملة.',
            'action_label' => 'استعراض الأدوات',
            'action_route' => route('tools.index'),
            'stage' => $nextStage,
        ];
    }

    private function stageLabel(int $stage): string
    {
        return StageCatalog::label($stage);
    }
}
