<?php

namespace App\Http\Controllers\Admin;

use App\Application\Admin\Dashboard\BuildOperationalAlertsAction;
use App\Domain\Account\Models\Account;
use App\Domain\AI\Models\AIGeneration;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Billing\Models\Plan;
use App\Domain\Client\Models\Client;
use App\Domain\Comment\Models\Comment;
use App\Domain\FeatureFlag\Models\FeatureFlag;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(BuildOperationalAlertsAction $operationalAlerts): View
    {
        $planDistribution = Plan::query()
            ->withCount('subscriptions')
            ->orderBy('monthly_price')
            ->get();

        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'accounts' => Account::query()->count(),
                'workspaces' => Workspace::query()->count(),
                'projects' => Project::query()->count(),
                'clients' => Client::query()->count(),
                'flags' => FeatureFlag::query()->whereIn('status', ['on', 'beta'])->count(),
                'tool_runs' => ToolRun::query()->count(),
                'ai_generations' => AIGeneration::query()->count(),
                'comments' => Comment::query()->count(),
            ],
            'planDistribution' => $planDistribution,
            'latestAuditLogs' => AuditLog::query()
                ->with('actor', 'workspace')
                ->latest('created_at')
                ->limit(8)
                ->get(),
            'operationalAlerts' => $operationalAlerts->handle(),
        ]);
    }
}
