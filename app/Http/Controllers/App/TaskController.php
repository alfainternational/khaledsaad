<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Support\Presentation\ProjectPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(private readonly ProjectPresenter $presenter) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorizeProject($request, $project);

        return view('app.tasks.index', [
            'project' => $this->presenter->card($project),
            'tasks' => $this->tasks($project),
        ]);
    }

    public function update(Request $request, Task $task): RedirectResponse
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

        return back()->with('status', 'حُدثت حالة المهمة.');
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function tasks(Project $project): array
    {
        $tasks = $project->tasks()->orderByDesc('priority')->get();

        return [
            'todo' => $tasks->where('status', Task::STATUS_TODO)->map(fn ($task) => $this->presenter->task($task))->values()->all(),
            'doing' => $tasks->where('status', Task::STATUS_DOING)->map(fn ($task) => $this->presenter->task($task))->values()->all(),
            'done' => $tasks->where('status', Task::STATUS_DONE)->map(fn ($task) => $this->presenter->task($task))->values()->all(),
        ];
    }
}
