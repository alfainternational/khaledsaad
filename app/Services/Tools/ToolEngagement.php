<?php

namespace App\Services\Tools;

use App\Models\Project;
use App\Models\Tool;
use App\Models\ToolRun;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * حالة تعامل المستخدم مع أداة معينة.
 *
 * سبب وجودها: عرض «ابدأ من هنا» لمن بدأ فعلًا يمحو عمله من نظره ويوحي
 * بأن ما أدخله ضاع. الزر يجب أن يقول له أين وقف، لا أن يعيده إلى الصفر.
 */
class ToolEngagement
{
    public const STATE_NEW = 'new';

    public const STATE_DRAFT = 'draft';

    public const STATE_RUNNING = 'running';

    public const STATE_READY = 'ready';

    public function __construct(private readonly AnswerCompleteness $completeness) {}

    /**
     * حالة الأداة داخل مشروع محدد.
     *
     * @return array<string, mixed>
     */
    public function forProject(Project $project, Tool $tool): array
    {
        return $this->describe(
            $project->runs()
                ->where('tool_version_id', $tool->current_version_id)
                ->with(['report', 'toolVersion.fields', 'answers'])
                ->latest('id')
                ->first(),
        );
    }

    /**
     * أحدث تعامل للمستخدم مع الأداة عبر كل مشاريعه.
     *
     * @return array<string, mixed>
     */
    public function forUser(User $user, Tool $tool): array
    {
        if (! $tool->isRunnable()) {
            return $this->describe(null);
        }

        return $this->describe(
            ToolRun::where('tool_version_id', $tool->current_version_id)
                ->whereHas('project.workspace', fn ($query) => $query->where('owner_id', $user->id))
                ->with(['report', 'project', 'toolVersion.fields', 'answers'])
                ->latest('id')
                ->first(),
        );
    }

    /**
     * خريطة حالات لكل الأدوات دفعة واحدة، بلا استعلام لكل بطاقة.
     *
     * @param  Collection<int, Tool>  $tools
     * @return array<string, array<string, mixed>>
     */
    public function mapForUser(User $user, Collection $tools): array
    {
        $versionIds = $tools->pluck('current_version_id')->filter()->all();

        $runs = ToolRun::whereIn('tool_version_id', $versionIds)
            ->whereHas('project.workspace', fn ($query) => $query->where('owner_id', $user->id))
            ->with(['report', 'project', 'toolVersion.fields', 'answers'])
            ->latest('id')
            ->get()
            ->unique('tool_version_id');

        return $tools->mapWithKeys(fn (Tool $tool) => [
            $tool->key => $this->describe($runs->firstWhere('tool_version_id', $tool->current_version_id)),
        ])->all();
    }

    /**
     * كل التشغيلات غير المكتملة للمستخدم — أساس قائمة «أكمل ما بدأته».
     *
     * @return Collection<int, ToolRun>
     */
    public function unfinishedFor(User $user): Collection
    {
        return ToolRun::whereHas('project.workspace', fn ($query) => $query->where('owner_id', $user->id))
            ->whereIn('status', [ToolRun::STATUS_DRAFT, ToolRun::STATUS_QUEUED, ToolRun::STATUS_PROCESSING])
            ->with(['project', 'toolVersion.tool', 'toolVersion.fields', 'answers', 'stages'])
            ->latest('id')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function describe(?ToolRun $run): array
    {
        if ($run === null) {
            return [
                'state' => self::STATE_NEW,
                'label' => 'ابدأ من هنا',
                'hint' => null,
                'run_uuid' => null,
                'project' => null,
                'report_id' => null,
                'percent' => 0,
                'can_restart' => false,
            ];
        }

        $project = $run->project;

        return match (true) {
            $run->status === ToolRun::STATUS_DRAFT => [
                'state' => self::STATE_DRAFT,
                'label' => 'أكمل من حيث وقفت',
                'hint' => $this->draftHint($run, $project?->name),
                'run_uuid' => $run->uuid,
                'project' => $project?->only(['name', 'slug']),
                'report_id' => null,
                'percent' => $this->completeness->percent($run),
                'can_restart' => true,
            ],
            in_array($run->status, [ToolRun::STATUS_QUEUED, ToolRun::STATUS_PROCESSING], true) => [
                'state' => self::STATE_RUNNING,
                'label' => 'تابع التحليل الجاري',
                'hint' => 'التحليل يعمل الآن على «'.($project?->name ?? 'مشروعك').'».',
                'run_uuid' => $run->uuid,
                'project' => $project?->only(['name', 'slug']),
                'report_id' => null,
                'percent' => $run->progressPercent(),
                'can_restart' => false,
            ],
            default => [
                'state' => self::STATE_READY,
                'label' => $run->report !== null ? 'راجع نتيجتك' : 'أعد المحاولة',
                'hint' => $run->report !== null
                    ? 'نتيجتك السابقة على «'.($project?->name ?? 'مشروعك').'» جاهزة.'
                    : 'المحاولة السابقة لم تكتمل، وإجاباتك محفوظة.',
                'run_uuid' => $run->uuid,
                'project' => $project?->only(['name', 'slug']),
                'report_id' => $run->report?->id,
                'percent' => 100,
                'can_restart' => true,
            ],
        };
    }

    private function draftHint(ToolRun $run, ?string $projectName): string
    {
        $percent = $this->completeness->percent($run);
        $where = $projectName !== null ? "«{$projectName}»" : 'مشروعك';

        return $percent > 0
            ? "أكملت {$percent}% من أسئلة {$where}."
            : "بدأت هذه على {$where} ولم تُجب بعد.";
    }
}
