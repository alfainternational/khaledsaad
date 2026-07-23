<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy
{
    public function view(User $user, Report $report): bool
    {
        return ProjectOwnership::owns($user, $report->project);
    }

    public function update(User $user, Report $report): bool
    {
        return $this->view($user, $report);
    }
}
