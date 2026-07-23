<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageRecord;
use App\Models\Report;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'tools_live' => Tool::runnable()->count(),
                'tools_total' => Tool::count(),
                'runs' => ToolRun::count(),
                'runs_completed' => ToolRun::whereIn('status', ['completed', 'partial'])->count(),
                'runs_failed' => ToolRun::where('status', 'failed')->count(),
                'reports' => Report::count(),
                'ai_cost_usd' => round((float) AiUsageRecord::sum('cost_usd'), 2),
                'ai_calls' => AiUsageRecord::count(),
            ],
            'recent_runs' => ToolRun::with(['toolVersion.tool', 'project'])
                ->latest('id')->limit(10)->get()
                ->map(fn (ToolRun $run) => [
                    'uuid' => $run->uuid,
                    'tool' => $run->toolVersion->tool->title,
                    'project' => $run->project->name,
                    'status' => $run->status,
                    'at' => $run->created_at->diffForHumans(),
                ])->all(),
        ]);
    }
}
