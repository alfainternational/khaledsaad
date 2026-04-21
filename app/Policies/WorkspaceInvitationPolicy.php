<?php

namespace App\Policies;

use App\Domain\Workspace\Models\WorkspaceInvitation;
use App\Models\User;

class WorkspaceInvitationPolicy
{
    public function delete(User $user, WorkspaceInvitation $invitation): bool
    {
        return in_array(
            $invitation->workspace?->members()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->value('role'),
            ['owner', 'admin'],
            true
        );
    }
}
