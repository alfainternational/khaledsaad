<?php

namespace App\Support\Contexts;

use App\Domain\Billing\Models\Plan;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;

class WorkspaceContext
{
    public function __construct(
        public readonly ?Workspace $workspace = null,
        public readonly ?User $user = null,
        public readonly ?Plan $plan = null,
    ) {}
}
