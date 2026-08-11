<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Kpi;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    use ResolvesWorkspace;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'unit' => 'nullable|string|max:40',
            'baseline' => 'nullable|numeric',
            'target' => 'nullable|numeric',
            'frequency' => 'nullable|in:weekly,monthly,quarterly',
        ]);

        $project->kpis()->create($data + ['frequency' => $data['frequency'] ?? 'monthly']);

        return back()->with('status', __('أُضيف المؤشر. سجّل أول قراءة لتبدأ المقارنة.'));
    }

    public function record(Request $request, Kpi $kpi): RedirectResponse
    {
        $this->authorizeProject($request, $kpi->project);

        $data = $request->validate([
            'value' => 'required|numeric',
            'recorded_at' => 'nullable|date',
        ]);

        $kpi->entries()->create([
            'value' => $data['value'],
            'recorded_at' => $data['recorded_at'] ?? now()->toDateString(),
        ]);

        return back()->with('status', __('سُجلت القراءة.'));
    }
}
