<?php

namespace App\Policies;

use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

class WorkspacePolicy
{
    public function view(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace) !== null;
    }

    public function switch(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace) !== null;
    }

    public function manageClients(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), ['owner', 'admin', 'editor'], true);
    }

    public function manageProjects(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), ['owner', 'admin', 'editor'], true);
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), ['owner', 'admin'], true);
    }

    public function useTools(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), ['owner', 'admin', 'editor', 'contributor'], true);
    }

    public function viewApprovals(User $user, Workspace $workspace): bool
    {
        return $this->role($user, $workspace) !== null;
    }

    public function requestApprovals(User $user, Workspace $workspace): bool
    {
        return in_array($this->role($user, $workspace), ['owner', 'admin', 'editor', 'contributor'], true);
    }

    private function role(User $user, Workspace $workspace): ?string
    {
        return $workspace->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');
    }
}
