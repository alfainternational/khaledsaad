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

class RegisterUserAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => 'ar',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);

            $account = Account::query()->create([
                'owner_user_id' => $user->id,
                'name' => ($data['account_name'] ?? null) ?: $user->name,
                'billing_email' => $user->email,
                'status' => 'active',
            ]);

            AccountMember::query()->create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'role' => 'owner',
                'status' => 'active',
                'invited_at' => now(),
            ]);

            $plan = Plan::query()->where('code', 'free')->firstOrFail();

            Subscription::query()->create([
                'account_id' => $account->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'current_period_end' => now()->addMonth(),
            ]);

            $workspace = Workspace::query()->create([
                'account_id' => $account->id,
                'name' => ($data['workspace_name'] ?? null) ?: ($user->name.' Workspace'),
                'type' => WorkspaceType::Personal->value,
                'status' => 'active',
            ]);

            WorkspaceMember::query()->create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'role' => WorkspaceRole::Owner->value,
                'status' => 'active',
                'invited_at' => now(),
            ]);

            return $user;
        });
    }
}
