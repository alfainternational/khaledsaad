<?php

namespace App\Application\Workspace;

use App\Domain\Workspace\Models\Workspace;
use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Models\User;
use Illuminate\Support\Str;

class InviteWorkspaceMemberAction
{
    /**
     * @param  array{email:string,role:string,expires_in_days?:int|null}  $data
     */
    public function handle(Workspace $workspace, array $data, User $actor): WorkspaceInvitation
    {
        return WorkspaceInvitation::query()->updateOrCreate(
            [
                'workspace_id' => $workspace->id,
                'email' => $data['email'],
                'status' => 'pending',
            ],
            [
                'role' => $data['role'],
                'token' => (string) Str::uuid(),
                'invited_by' => $actor->id,
                'expires_at' => now()->addDays((int) ($data['expires_in_days'] ?? 7)),
                'accepted_at' => null,
            ],
        );
    }
}
