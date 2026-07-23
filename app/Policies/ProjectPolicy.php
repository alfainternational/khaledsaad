<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return ProjectOwnership::owns($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return ProjectOwnership::owns($user, $project);
    }
}
