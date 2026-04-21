<?php

namespace App\Policies;

use App\Domain\Project\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $this->role($user, $project) !== null;
    }

    public function update(User $user, Project $project): bool
    {
        return in_array($this->role($user, $project), ['owner', 'admin', 'editor', 'contributor'], true);
    }

    public function delete(User $user, Project $project): bool
    {
        return in_array($this->role($user, $project), ['owner', 'admin'], true);
    }

    private function role(User $user, Project $project): ?string
    {
        return $project->workspace?->members()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('role');
    }
}
