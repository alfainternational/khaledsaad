<?php

namespace App\Application\Auth;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountMember;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Workspace\Enums\WorkspaceRole;
use App\Domain\Workspace\Enums\WorkspaceType;
use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnsureUserWorkspaceAccessAction
{
    public function handle(User $user): Workspace
    {
        $workspace = $user->activeWorkspaces()
            ->with('account.subscription.plan')
            ->first();

        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        return DB::transaction(function () use ($user): Workspace {
            $account = Account::query()->firstOrCreate(
                ['owner_user_id' => $user->id],
                [
                    'name' => $user->name,
                    'billing_email' => $user->email,
                    'status' => 'active',
                ],
            );

            AccountMember::query()->firstOrCreate(
                [
                    'account_id' => $account->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => 'owner',
                    'status' => 'active',
                    'invited_at' => now(),
                ],
            );

            if (! $account->subscription()->exists()) {
                $plan = Plan::query()->where('code', 'free')->first();

                if ($plan) {
                    Subscription::query()->create([
                        'account_id' => $account->id,
                        'plan_id' => $plan->id,
                        'status' => 'active',
                        'current_period_end' => now()->addMonth(),
                    ]);
                }
            }

            $workspace = Workspace::query()->firstOrCreate(
                [
                    'account_id' => $account->id,
                    'type' => WorkspaceType::Personal->value,
                ],
                [
                    'name' => $user->name.' Workspace',
                    'status' => 'active',
                ],
            );

            WorkspaceMember::query()->firstOrCreate(
                [
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => WorkspaceRole::Owner->value,
                    'status' => 'active',
                    'invited_at' => now(),
                ],
            );

            return $workspace->fresh(['account.subscription.plan']) ?? $workspace;
        });
    }
}
