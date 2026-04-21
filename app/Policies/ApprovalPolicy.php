<?php

namespace App\Policies;

use App\Domain\Approval\Models\Approval;
use App\Models\User;

class ApprovalPolicy
{
    public function view(User $user, Approval $approval): bool
    {
        return $approval->workspace()
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id)->where('status', 'active'))
            ->exists();
    }

    public function review(User $user, Approval $approval): bool
    {
        $role = $approval->workspace
            ?->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');

        return in_array($role, ['owner', 'admin', 'editor', 'client'], true);
    }
}
