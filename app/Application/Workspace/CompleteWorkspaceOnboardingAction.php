<?php

namespace App\Application\Workspace;

use App\Domain\Account\Models\Account;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Jobs\RunProjectIntelligenceAuditJob;
use App\Models\User;
use App\Support\Dashboard\PathRecommendationService;
use App\Support\Intelligence\MarketingIntelligenceService;
use App\Support\Projects\ProjectMarketingBriefStore;
use App\Support\Workspaces\OnboardingState;
use App\Support\Workspaces\WorkspaceJourneyStore;
use App\Support\Workspaces\WorkspaceProfileStore;
use Illuminate\Support\Facades\DB;

class CompleteWorkspaceOnboardingAction
{
    public function __construct(
        private readonly WorkspaceProfileStore $profileStore,
        private readonly WorkspaceJourneyStore $journeyStore,
        private readonly PathRecommendationService $pathRecommendationService,
        private readonly ProjectMarketingBriefStore $projectMarketingBriefStore,
        private readonly MarketingIntelligenceService $marketingIntelligenceService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(
        Workspace $workspace,
        Account $account,
        User $user,
        array $data,
        OnboardingState $state,
    ): void {
        $project = DB::transaction(function () use ($workspace, $account, $user, $data, $state): Project {
            $user->forceFill([
                'name' => $data['account_name'],
            ])->save();

            $account->forceFill([
                'name' => $data['account_name'],
                'billing_email' => $user->email,
            ])->save();

            $workspace->forceFill([
                'name' => $data['workspace_name'],
                'type' => $data['workspace_type'],
            ])->save();

            // بناء العميل تلقائياً من مدخلات الـ onboarding: نُثري سجلّه بالموقع والقطاع
            // والسوق والجمهور حتى يدخل السياق التحليلي جاهزاً بدل «عميل بلا بيانات».
            $clientAudience = $data['brief_ideal_customer'] ?? ($data['audience'] ?? null);
            $client = Client::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $data['client_name'],
                'contact_info' => array_filter([
                    'company' => $data['client_name'],
                    'website' => $data['primary_domain'] ?? null,
                    'sector' => $data['sector'] ?? null,
                    'market' => $data['country'] ?? null,
                    'audience' => $clientAudience,
                    'notes' => trim(implode("\n", array_filter([
                        ! empty($data['brief_business_summary']) ? 'النشاط: '.$data['brief_business_summary'] : null,
                        ! empty($clientAudience) ? 'العميل المثالي: '.$clientAudience : null,
                        ! empty($data['brief_offer']) ? 'العرض: '.$data['brief_offer'] : null,
                    ]))) ?: null,
                ], fn ($v) => $v !== null && $v !== ''),
                'status' => 'active',
            ]);

            $project = Project::query()->create([
                'workspace_id' => $workspace->id,
                'client_id' => $client->id,
                'name' => $data['project_name'],
                'stage' => $data['project_stage'],
                'status' => 'active',
                'sector' => $data['sector'] ?? 'general_business',
                'market_country' => $data['country'] ?? null,
                'primary_domain' => $data['primary_domain'] ?? null,
                'official_social_links_json' => $data['official_social_links_json'] ?? [],
                'verified_social_profiles_json' => $data['verified_social_profiles_json'] ?? [],
                'competitors_json' => $data['competitors_json'] ?? [],
                'analysis_goals_json' => $data['analysis_goals_json'] ?? [],
                'monitoring_enabled' => $data['monitoring_enabled'] ?? false,
            ]);

            $this->profileStore->put($workspace, [
                'persona' => $data['persona'],
                'awareness_level' => $data['awareness_level'],
                'primary_goal' => $data['primary_goal'],
                'recommended_path' => ($data['recommended_path'] ?? null)
                    ?: $this->pathRecommendationService->recommend(
                        $data['persona'],
                        $data['primary_goal'],
                        $data['awareness_level'],
                    ),
                'audience' => $data['audience'],
                'country' => $data['country'],
                'content_locale' => $data['content_locale'],
                'current_challenge' => $data['current_challenge'] ?? null,
            ]);

            $this->journeyStore->putProfile($workspace, [
                'persona' => $data['persona'],
                'awareness_level' => $data['awareness_level'],
                'primary_goal' => $data['primary_goal'],
                'recommended_path' => ($data['recommended_path'] ?? null)
                    ?: $this->pathRecommendationService->recommend(
                        $data['persona'],
                        $data['primary_goal'],
                        $data['awareness_level'],
                    ),
                'current_stage' => (int) $project->stage,
                'current_step' => null,
                'completion_snapshot' => [
                    'completed_count' => 0,
                    'completed_tools' => [],
                ],
            ], $project);

            $this->projectMarketingBriefStore->put($workspace, $project, [
                'business' => [
                    'summary' => $data['brief_business_summary'] ?? null,
                    'offer' => $data['brief_offer'] ?? null,
                    'market' => $data['country'] ?? null,
                ],
                'audience' => [
                    'ideal_customer' => $data['brief_ideal_customer'] ?? ($data['audience'] ?? null),
                ],
                'goals' => [
                    'primary_goal' => $data['brief_primary_goal'] ?? ($data['primary_goal'] ?? null),
                    'success_metric' => $data['brief_success_metric'] ?? null,
                ],
                'current_marketing' => [
                    'channels' => $data['brief_current_channels'] ?? null,
                ],
                'execution' => [
                    'priority' => $data['brief_priority'] ?? ($data['current_challenge'] ?? null),
                    'next_asset' => 'التشخيص',
                ],
            ]);

            $state->markCompleted($workspace, [
                'client_name' => $client->name,
                'project_name' => $data['project_name'],
            ]);

            return $project;
        });

        // إطلاق تدقيق Marketing Intelligence تلقائياً بعد الـ onboarding متى توفّر ما يُحلَّل،
        // حتى تكون نتائج التشخيص جاهزة لتعبئة مسودّات الأدوات عند أول استخدام.
        $hasCrawlableTarget = ! empty($data['primary_domain'])
            || ! empty($data['official_social_links_json'])
            || ! empty($data['competitors_json']);

        if ($hasCrawlableTarget) {
            $auditRun = $this->marketingIntelligenceService->queue($project->fresh(), $workspace, 'onboarding');
            RunProjectIntelligenceAuditJob::dispatch($auditRun->id);
        }
    }
}
