<?php

namespace App\Policies;

use App\Domain\Workspace\Models\WorkspaceMember;
use App\Models\User;

class WorkspaceMemberPolicy
{
    public function delete(User $user, WorkspaceMember $member): bool
    {
        return in_array(
            $member->workspace?->members()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->value('role'),
            ['owner', 'admin'],
            true
        );
    }
}
