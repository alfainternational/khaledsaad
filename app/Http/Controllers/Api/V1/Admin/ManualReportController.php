<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Admin\AdminManualReportController;
use App\Http\Controllers\Controller;
use App\Models\ToolRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function show(ToolRun $run, AdminManualReportController $manual): JsonResponse
    {
        return response()->json(['data' => [
            'run' => [
                'uuid' => $run->uuid,
                'tool' => $run->toolVersion->tool->title,
                'project' => $run->project->name,
                'status' => $run->status,
            ],
            'package' => $manual->export($run)->getData(true),
        ]]);
    }

    public function store(
        Request $request,
        ToolRun $run,
        AdminManualReportController $manual,
    ): JsonResponse {
        if ($run->status === ToolRun::STATUS_COMPLETED) {
            return response()->json(['message' => __('اكتمل هذا التقرير مسبقاً.')], 409);
        }

        if (is_array($request->input('payload'))) {
            $request->merge([
                'payload' => json_encode($request->input('payload'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }

        $manual->store($request, $run);

        return response()->json(['data' => [
            'uuid' => $run->uuid,
            'status' => $run->fresh()->status,
            'report_id' => $run->fresh()->report?->id,
        ]]);
    }
}
