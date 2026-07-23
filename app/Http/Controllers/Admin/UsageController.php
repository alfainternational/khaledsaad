<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageRecord;
use App\Models\Tool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * لوحة تكلفة الذكاء الاصطناعي: بدونها لا يعرف مالك المنصة أي أداة
 * تحرق أكثر مما تعيد.
 */
class UsageController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.usage', $this->payload($request));
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(Request $request): array
    {
        $since = now()->subDays($request->integer('days', 30) ?: 30);

        $records = AiUsageRecord::where('created_at', '>=', $since);

        return [
            'days' => $request->integer('days', 30) ?: 30,
            'totals' => [
                'runs' => (clone $records)->distinct('tool_run_id')->count('tool_run_id'),
                'calls' => (clone $records)->count(),
                'cost_usd' => round((float) (clone $records)->sum('cost_usd'), 4),
                'input_tokens' => (int) (clone $records)->sum('input_tokens'),
                'output_tokens' => (int) (clone $records)->sum('output_tokens'),
                'avg_latency_ms' => (int) round((float) (clone $records)->avg('latency_ms')),
                'invalid_outputs' => (clone $records)->where('status', 'invalid_output')->count(),
            ],
            'by_model' => (clone $records)
                ->select('model', DB::raw('count(*) as calls'), DB::raw('sum(cost_usd) as cost'), DB::raw('avg(latency_ms) as latency'))
                ->groupBy('model')
                ->get()
                ->map(fn ($row) => [
                    'model' => $row->model,
                    'calls' => (int) $row->calls,
                    'cost_usd' => round((float) $row->cost, 4),
                    'avg_latency_ms' => (int) round((float) $row->latency),
                ])->all(),
            'by_stage' => (clone $records)
                ->select('stage', DB::raw('count(*) as calls'), DB::raw('sum(cost_usd) as cost'))
                ->groupBy('stage')
                ->orderByDesc('cost')
                ->get()
                ->map(fn ($row) => [
                    'stage' => $row->stage ?? '—',
                    'calls' => (int) $row->calls,
                    'cost_usd' => round((float) $row->cost, 4),
                ])->all(),
            'tools' => Tool::orderBy('sort_order')->get(['key', 'title', 'status'])->all(),
        ];
    }
}
