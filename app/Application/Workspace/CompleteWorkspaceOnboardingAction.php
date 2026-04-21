<?php

namespace App\Application\Workspace;

use App\Domain\Account\Models\Account;
use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Dashboard\PathRecommendationService;
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
        DB::transaction(function () use ($workspace, $account, $user, $data, $state): void {
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

            $client = Client::query()->create([
                'workspace_id' => $workspace->id,
                'name' => $data['client_name'],
                'contact_info' => [
                    'company' => $data['client_name'],
                ],
                'status' => 'active',
            ]);

            $project = Project::query()->create([
                'workspace_id' => $workspace->id,
                'client_id' => $client->id,
                'name' => $data['project_name'],
                'stage' => $data['project_stage'],
                'status' => 'active',
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

            $state->markCompleted($workspace, [
                'client_name' => $client->name,
                'project_name' => $data['project_name'],
            ]);
        });
    }
}
