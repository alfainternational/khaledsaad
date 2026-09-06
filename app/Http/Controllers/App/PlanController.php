<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Support\Presentation\ProjectPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * «الخطة والمهام» عبر المشاريع كلها — القسم الذي كانت الملاحة تعد به
 * ثم تُلقي بالمستخدم في صفحة المشاريع.
 *
 * وجود المهام لكل مشروع على حدة كان يخفي السؤال الوحيد الذي يعود
 * المستخدم أسبوعيًا ليسأله: «ما الذي عليّ فعله هذا الأسبوع؟». الإجابة
 * تحتاج نظرةً واحدة عبر مشاريعه، لا تنقّلًا بينها.
 */
final class PlanController extends Controller
{
    public function __construct(private readonly ProjectPresenter $presenter) {}

    public function index(Request $request): View
    {
        $projects = $this->userProjects($request);

        $tasks = Task::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->with(['project:id,name,slug', 'recommendation:id,report_id'])
            ->orderByDesc('priority')
            ->orderBy('due_date')
            ->get();

        return view('app.plan.index', [
            'projects_count' => $projects->count(),
            'groups' => $this->group($tasks),
            // العدّ يُحسب مرة هنا لا في القالب: رقمٌ في العرض رقمٌ بلا مصدر.
            'total' => $tasks->count(),
        ]);
    }

    /**
     * التجميع بما يقود قرارًا، لا بما يطابق أعمدة الجدول.
     *
     * «هذا الأسبوع» يسبق كل شيء لأنه وحده يجيب على سؤال العودة. والمتأخر
     * يدخل فيه لا في مجموعة منفصلة: فصلُه يجعل تجاهله أسهل.
     *
     * @param  Collection<int, Task>  $tasks
     * @return array<string, array{label: string, hint: string, tasks: array<int, array<string, mixed>>}>
     */
    private function group(Collection $tasks): array
    {
        $horizon = now()->endOfWeek();

        // المقترحة تُستبعد من «هذا الأسبوع»: لم يتبنَّها بعد، ووضعُها في
        // قائمة المستحق يجعله يرى تأخّرًا في عملٍ لم يوافق عليه أصلًا.
        $adopted = $tasks->where('status', '!=', Task::STATUS_SUGGESTED);

        $thisWeek = $adopted->filter(fn (Task $task) => $task->status !== Task::STATUS_DONE
            && $task->due_date !== null
            && $task->due_date->lte($horizon));

        $doing = $adopted->where('status', Task::STATUS_DOING)
            ->reject(fn (Task $task) => $thisWeek->contains($task));

        $later = $adopted->where('status', Task::STATUS_TODO)
            ->reject(fn (Task $task) => $thisWeek->contains($task));

        return [
            'suggested' => [
                'label' => __('مقترحة عليك'),
                'hint' => __('وُلّدت من توصيات تقاريرك. تبنَّ ما تريد تنفيذه فعلًا.'),
                'tasks' => $this->present($tasks->where('status', Task::STATUS_SUGGESTED)),
            ],
            'this_week' => [
                'label' => __('هذا الأسبوع'),
                'hint' => __('مستحقة أو متأخرة — ابدأ من هنا.'),
                'tasks' => $this->present($thisWeek),
            ],
            'doing' => [
                'label' => __('قيد التنفيذ'),
                'hint' => __('بدأتها ولم تُغلقها بعد.'),
                'tasks' => $this->present($doing),
            ],
            'later' => [
                'label' => __('في الانتظار'),
                'hint' => __('مرتّبة بالأثر مقابل الجهد — الأعلى أثرًا وأقلّها جهدًا أولًا.'),
                'tasks' => $this->present($later),
            ],
            'done' => [
                'label' => __('منجزة'),
                'hint' => __('ما أنجزته يرفع جاهزية مشروعك.'),
                'tasks' => $this->present($tasks->where('status', Task::STATUS_DONE)),
            ],
        ];
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function present(Collection $tasks): array
    {
        return $tasks->map(fn (Task $task) => [
            ...$this->presenter->task($task),
            // المهمة عبر المشاريع تحتاج أن تقول لأيّ مشروع تنتمي.
            'project' => [
                'name' => $task->project?->name,
                'slug' => $task->project?->slug,
            ],
            // مصدر المهمة: مهمةٌ بلا تقرير تبدو أمرًا صدر بلا سبب.
            'report_id' => $task->recommendation?->report_id,
            'is_suggested' => $task->status === Task::STATUS_SUGGESTED,
        ])->values()->all();
    }

    /**
     * @return Collection<int, Project>
     */
    private function userProjects(Request $request): Collection
    {
        return Project::query()
            ->whereHas('workspace', fn ($query) => $query->where('owner_id', $request->user()->id))
            ->get(['id', 'name', 'slug']);
    }
}
