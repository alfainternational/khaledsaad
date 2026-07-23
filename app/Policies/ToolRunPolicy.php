<?php

namespace App\Policies;

use App\Models\ToolRun;
use App\Models\User;

class ToolRunPolicy
{
    public function view(User $user, ToolRun $run): bool
    {
        return ProjectOwnership::owns($user, $run->project);
    }

    public function update(User $user, ToolRun $run): bool
    {
        return $this->view($user, $run);
    }
}
