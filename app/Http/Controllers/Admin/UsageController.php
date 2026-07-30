<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsageRecord;
use App\Models\Tool;
use App\Modules\Measurement\Models\QueryBudget;
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
            'budgets' => $this->budgets(),
        ];
    }

    /**
     * سقوف الاستعلامات لشهر هذه اللحظة، الأقرب إلى حدّه أولًا.
     *
     * السقف كان مُنفَّذًا بالكامل — حجزٌ قبل الطابور وتنبيه ٨٠٪ ورفض ١٠٠٪ —
     * وغير مرئي لأحد. صفحة التكلفة تعرف ما أنفقته النماذج بعد الاستدعاء، ولا
     * تعرف من اقترب من حدّه ولا من رُفض له استطلاع ولماذا. أداةُ تحكّم في
     * التكلفة لا يراها المسؤول عن التكلفة تُدار بالفاتورة آخر الشهر.
     *
     * الترتيب بالنسبة لا بالمطلق: مساحة استهلكت ٩٠ من ١٠٠ أقرب إلى التوقف من
     * أخرى استهلكت ٢٠٠ من ١٠٠٠.
     *
     * @return array<int, array<string, mixed>>
     */
    private function budgets(): array
    {
        return QueryBudget::query()
            ->where('period', now()->format('Y-m'))
            ->with('workspace:id,name')
            ->get()
            ->map(fn (QueryBudget $budget) => [
                'workspace' => $budget->workspace?->name ?? '—',
                'committed' => $budget->committed(),
                'limit' => $budget->monthly_limit,
                'remaining' => $budget->remaining(),
                'usage_percent' => (int) round($budget->usageRatio() * 100),
                'cost_usd' => round((float) $budget->cost_usd, 4),

                // التنبيه أُرسل أم لا: «٨٠٪ ولم يُنبَّه» عطلٌ لا حالة.
                'warned' => $budget->warned_at !== null,
                'exhausted' => $budget->remaining() === 0,
            ])
            ->sortByDesc('usage_percent')
            ->values()
            ->all();
    }
}
