<?php

namespace App\Domain\Workspace\Enums;

enum WorkspaceRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Contributor = 'contributor';
    case Viewer = 'viewer';
    case Client = 'client';
}
