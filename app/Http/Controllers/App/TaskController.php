<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Modules\Execution\TaskGuideRequest;
use App\Support\Presentation\ProjectPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaskController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly ProjectPresenter $presenter,
        private readonly TaskGuideRequest $guides,
    ) {}

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

        return back()->with('status', __('حُدثت حالة المهمة.'));
    }

    /**
     * تطوير المهمة: كيف تُنفَّذ، متى، أين، ماذا تقدّم، وأمثلة تُنسخ وتُستعمل.
     *
     * إعادة الطلب مسموحة والدليل يُكتب فوق سابقه — المهمة تظل حيّة (§٤.٥).
     * ما يُمنع هو طلبٌ ثانٍ وأول في الطابور، فهو صرفٌ مكرر على نفس النتيجة.
     */
    public function develop(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $task);

        if ($task->guide_status === Task::GUIDE_PENDING) {
            return back()->with('status', __('دليل هذه المهمة قيد التطوير الآن.'));
        }

        $this->guides->dispatch($task);

        return back()->with('status', __('بدأ تطوير المهمة. ستجد الخطوات والأمثلة هنا خلال دقيقة.'));
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
