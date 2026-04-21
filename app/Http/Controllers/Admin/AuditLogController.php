<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Workspace\Models\Workspace;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->buildQuery($request);

        return view('admin.audit-logs.index', [
            'auditLogs' => $query->paginate(20)->withQueryString(),
            'actors' => User::query()->where('is_super_admin', true)->orderBy('name')->get(),
            'workspaces' => Workspace::query()->orderBy('name')->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $logs = $this->buildQuery($request)->get();

        return response()->streamDownload(function () use ($logs): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['date', 'actor', 'action', 'target_type', 'target_id', 'workspace']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->created_at?->toDateTimeString(),
                    $log->actor?->email,
                    $log->action,
                    $log->target_type,
                    $log->target_id,
                    $log->workspace?->name,
                ]);
            }

            fclose($handle);
        }, 'audit-logs.csv');
    }

    private function buildQuery(Request $request): Builder
    {
        return AuditLog::query()
            ->with('actor', 'workspace')
            ->when($request->filled('actor_user_id'), fn ($query) => $query->where('actor_user_id', $request->integer('actor_user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'like', '%'.$request->string('action')->value().'%'))
            ->when($request->filled('target_type'), fn ($query) => $query->where('target_type', $request->string('target_type')->value()))
            ->when($request->filled('workspace_id'), fn ($query) => $query->where('workspace_id', $request->integer('workspace_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->latest('created_at');
    }
}
