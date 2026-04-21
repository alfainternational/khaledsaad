<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Workspace\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceIndexController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = Workspace::query()
            ->whereHas('members', function ($query) use ($user): void {
                $query->where('user_id', $user->id)->where('status', 'active');
            })
            ->orderBy('name')
            ->get(['public_id', 'name', 'type', 'status']);

        return response()->json([
            'data' => $rows->map(fn (Workspace $w): array => [
                'public_id' => $w->public_id,
                'name' => $w->name,
                'type' => $w->type,
                'status' => $w->status,
            ])->values()->all(),
        ]);
    }
}
