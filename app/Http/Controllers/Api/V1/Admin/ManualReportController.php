<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ToolRun;
use Illuminate\Http\JsonResponse;

class ManualReportController extends Controller
{
    public function index(): JsonResponse
    {
        $query = fn () => ToolRun::where('delivery_mode', 'manual')
            ->with(['project', 'toolVersion.tool', 'report'])
            ->latest('id');

        $present = fn (ToolRun $run) => [
            'uuid' => $run->uuid,
            'tool' => $run->toolVersion->tool->title,
            'project' => $run->project->name,
            'status' => $run->status,
            'report_id' => $run->report?->id,
            'updated_at' => $run->updated_at?->toISOString(),
        ];

        return response()->json(['data' => [
            'pending' => $query()->whereIn('status', [
                ToolRun::STATUS_QUEUED,
                ToolRun::STATUS_PROCESSING,
            ])->get()->map($present)->all(),
            'completed' => $query()->where('status', ToolRun::STATUS_COMPLETED)
                ->limit(50)->get()->map($present)->all(),
        ]]);
    }
}
