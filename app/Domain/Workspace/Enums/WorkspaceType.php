<?php

namespace App\Domain\Workspace\Enums;

enum WorkspaceType: string
{
    case Personal = 'personal';
    case Team = 'team';
    case Agency = 'agency';
}
