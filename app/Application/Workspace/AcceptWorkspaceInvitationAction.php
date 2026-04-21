<?php

namespace App\Application\Workspace;

use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptWorkspaceInvitationAction
{
    public function handle(WorkspaceInvitation $invitation, User $user): WorkspaceMember
    {
        abort_unless($invitation->status === 'pending', 422, 'الدعوة غير متاحة.');
        abort_unless($invitation->expires_at === null || $invitation->expires_at->isFuture(), 422, 'انتهت صلاحية الدعوة.');
        abort_unless(strtolower($invitation->email) === strtolower($user->email), 403, 'هذه الدعوة مخصصة لبريد آخر.');

        return DB::transaction(function () use ($invitation, $user): WorkspaceMember {
            $member = WorkspaceMember::query()->updateOrCreate(
                [
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->id,
                ],
                [
                    'role' => $invitation->role,
                    'status' => 'active',
                    'invited_at' => $invitation->created_at,
                ],
            );

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            return $member;
        });
    }
}
