<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Concerns\ResolvesWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Modules\Execution\TaskGuideRequest;
use App\Modules\Insights\FunnelRecorder;
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
        private readonly FunnelRecorder $funnel,
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
            // `suggested` مقبولة كي يستطيع المستخدم إعادة مهمة إلى الاقتراح
            // بدل حذفها: التراجع عن التبنّي ليس إسقاطًا للتوصية.
            'status' => 'required|in:suggested,todo,doing,done',
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
     * تبنّي مهمة مقترحة — النقلة من «قرأت» إلى «سأنفّذ».
     *
     * التبنّي فعلٌ صغير مقصود: الخطة تُقترح كاملة تلقائيًّا، ويختار منها
     * المستخدم ما يلتزم به. بلا هذه الخطوة يواجه أربع عشرة مهمة مفتوحة
     * لم يوافق على واحدة منها، فيهجرها كلها.
     */
    public function adopt(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $task);

        if ($task->status !== Task::STATUS_SUGGESTED) {
            return back();
        }

        $task->update([
            'status' => Task::STATUS_TODO,
            // المالك يُسجَّل عند التبنّي لا عند التوليد: المولَّد تلقائيًّا
            // لا صاحب له بعد، ومن يتبنّاه هو من سيُنبَّه عليه.
            'owner_id' => $task->owner_id ?? $request->user()->id,
        ]);

        // المؤشر الحاكم للاحتفاظ: من أنجز مهمة رأى أثرًا، ومن رأى أثرًا
        // يجدّد. وقياسه يبدأ من التبنّي.
        $this->funnel->record($request, FunnelRecorder::TASK_ADOPTED, ['task' => $task->id]);

        return back()->with('status', __('أُضيفت المهمة إلى خطتك.'));
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
            // المقترحة لها عمودها: بلا هذا تختفي مهامٌ وُلّدت تلقائيًّا من
            // لوحة المشروع، فيرى المستخدم صفرًا بينما الخطة موجودة.
            'suggested' => $tasks->where('status', Task::STATUS_SUGGESTED)->map(fn ($task) => $this->presenter->task($task))->values()->all(),
            'todo' => $tasks->where('status', Task::STATUS_TODO)->map(fn ($task) => $this->presenter->task($task))->values()->all(),
            'doing' => $tasks->where('status', Task::STATUS_DOING)->map(fn ($task) => $this->presenter->task($task))->values()->all(),
            'done' => $tasks->where('status', Task::STATUS_DONE)->map(fn ($task) => $this->presenter->task($task))->values()->all(),
        ];
    }
}
