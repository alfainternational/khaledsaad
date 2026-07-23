<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return ProjectOwnership::owns($user, $task->project);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
