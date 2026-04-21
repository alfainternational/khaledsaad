<?php

namespace App\Support\Contexts;

use App\Models\User;

class AdminContext
{
    public function __construct(
        public readonly ?User $user = null,
    ) {}
}
