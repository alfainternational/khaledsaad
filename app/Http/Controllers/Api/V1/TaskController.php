<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Kpi;
use App\Models\Project;
use App\Models\Task;
use App\Support\Presentation\ProjectPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TaskController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(private readonly ProjectPresenter $presenter) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $tasks = $project->tasks()->orderByDesc('priority')->get();

        return response()->json([
            'data' => [
                'todo' => $this->group($tasks, Task::STATUS_TODO),
                'doing' => $this->group($tasks, Task::STATUS_DOING),
                'done' => $this->group($tasks, Task::STATUS_DONE),
            ],
        ]);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $task);

        $data = $request->validate([
            'status' => 'required|in:todo,doing,done',
            'due_date' => 'nullable|date',
        ]);

        $task->update([
            'status' => $data['status'],
            'due_date' => $data['due_date'] ?? $task->due_date,
            'completed_at' => $data['status'] === Task::STATUS_DONE ? now() : null,
        ]);

        return response()->json(['data' => $this->presenter->task($task->refresh())]);
    }

    /**
     * نظير `App\TaskController::develop` — نفس القاعدة في الاثنين لأن
     * التطبيق والويب نسختان من منتج واحد لا تنفيذان متوازيان.
     */
    public function develop(Request $request, Task $task): JsonResponse
    {
        $this->authorizeTask($request, $task);

        if ($task->guide_status !== Task::GUIDE_PENDING) {
            $this->guides->dispatch($task);
        }

        return response()->json(['data' => $this->presenter->task($task->refresh())], 202);
    }

    public function storeKpi(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'unit' => 'nullable|string|max:40',
            'baseline' => 'nullable|numeric',
            'target' => 'nullable|numeric',
            'frequency' => 'nullable|in:weekly,monthly,quarterly',
        ]);

        $kpi = $project->kpis()->create($data + ['frequency' => $data['frequency'] ?? 'monthly']);

        return response()->json(['data' => ['id' => $kpi->id, 'name' => $kpi->name]], 201);
    }

    public function recordKpi(Request $request, Kpi $kpi): JsonResponse
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

        return response()->json([
            'data' => [
                'latest' => $kpi->latestValue(),
                'attainment_percent' => $kpi->attainmentPercent(),
            ],
        ], 201);
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function group($tasks, string $status): array
    {
        return $tasks->where('status', $status)
            ->map(fn (Task $task) => $this->presenter->task($task))
            ->values()
            ->all();
    }
}
