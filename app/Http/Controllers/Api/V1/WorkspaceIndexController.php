<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Models\Workspace;
use App\Http\Resources\V1\WorkspaceSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkspaceIndexController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $rows = Workspace::query()
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('user_id', $user->id)->where('status', 'active');
            })
            ->orderBy('name')
            ->get(['id', 'public_id', 'name', 'type', 'status']);

        return WorkspaceSummaryResource::collection($rows);
    }
}
