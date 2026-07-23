<?php

namespace App\Services\Growth;

use App\Models\Project;
use App\Models\ProjectCompetitor;
use App\Models\PulseDigest;
use App\Models\Task;
use Illuminate\Support\Carbon;

/**
 * مؤلّف النبض الأسبوعي: يحوّل إشارات المشروع المتناثرة إلى ثلاث رسائل
 * مفهومة وخطوة واحدة مقترحة.
 *
 * حتمي بالكامل عمدًا: النبض يصل كل أسبوع مهما حدث، بلا تكلفة نموذج
 * وبلا احتمال فشل — نفس فلسفة الأرضية الحتمية في التقارير.
 */
class PulseComposer
{
    public function __construct(
        private readonly LiveReportChecker $checker,
        private readonly NextToolSuggester $suggester,
    ) {}

    public function compose(Project $project, Carbon $weekStart): PulseDigest
    {
        $items = $this->items($project, $weekStart);

        return PulseDigest::updateOrCreate(
            ['project_id' => $project->id, 'week_start' => $weekStart->toDateString()],
            [
                'workspace_id' => $project->workspace_id,
                'items' => array_slice($items, 0, 4),
                'next_step' => $this->nextStep($project, $items),
            ],
        );
    }

    /**
     * الإشارات مرتبة بالأهمية: ما يستوجب فعلًا قبل ما يطمئن.
     *
     * @return array<int, array{type: string, title: string, body: string}>
     */
    private function items(Project $project, Carbon $weekStart): array
    {
        $items = [];
        $latestReport = $project->reports()->latest('created_at')->first();

        // 1) انحراف البيانات: التقرير الأخير لم يعد يمثل الواقع.
        if ($latestReport?->watcher?->isActive() ?? false) {
            $changes = $this->checker->check($latestReport->watcher);

            if ($changes !== []) {
                $items[] = [
                    'type' => 'drift',
                    'title' => 'مشروعك تغيّر بعد آخر تحليل',
                    'body' => $changes[0]['text'].(count($changes) > 1 ? ' وهناك '.(count($changes) - 1).' تغييرات أخرى.' : ''),
                ];
            }
        }

        // 2) المهام المتأخرة: أوضح فجوة بين النية والتنفيذ.
        $overdue = $project->tasks()
            ->where('status', '!=', Task::STATUS_DONE)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->count();

        if ($overdue > 0) {
            $items[] = [
                'type' => 'overdue',
                'title' => "لديك {$overdue} ".($overdue === 1 ? 'مهمة متأخرة' : 'مهام متأخرة'),
                'body' => 'التوصيات التي تحولت إلى مهام ثم تأخرت هي قيمة اشتريتها ولم تقبضها بعد.',
            ];
        }

        // 3) منافسون مرشحون بانتظار الحسم.
        $candidates = $project->competitors()
            ->where('status', ProjectCompetitor::STATUS_CANDIDATE)
            ->count();

        if ($candidates > 0) {
            $items[] = [
                'type' => 'competitors',
                'title' => "{$candidates} ".($candidates === 1 ? 'منافس مرشّح ينتظر' : 'منافسون مرشّحون ينتظرون').' تأكيدك',
                'body' => 'أكّد من ينافسك فعلًا واستبعد الباقي — دقة قائمة المنافسين ترفع دقة كل تحليل قادم.',
            ];
        }

        // 4) إنجاز الأسبوع: التقدم يستحق أن يُرى.
        $doneThisWeek = $project->tasks()
            ->where('status', Task::STATUS_DONE)
            ->where('completed_at', '>=', $weekStart->copy()->subWeek())
            ->count();

        if ($doneThisWeek > 0) {
            $items[] = [
                'type' => 'progress',
                'title' => "أنجزت {$doneThisWeek} ".($doneThisWeek === 1 ? 'مهمة' : 'مهام').' هذا الأسبوع',
                'body' => 'استمر بنفس الإيقاع — الدرجة القادمة ستعكس هذا الشغل.',
            ];
        }

        // 5) التقادم: تقرير قديم يفقد صلاحيته كأساس للقرار.
        $staleDays = (int) config('growth.stale_days', 45);

        if ($latestReport !== null && $latestReport->created_at->lt(now()->subDays($staleDays))) {
            $age = (int) $latestReport->created_at->diffInDays(now());
            $items[] = [
                'type' => 'stale',
                'title' => "آخر تحليل لك عمره {$age} يومًا",
                'body' => 'السوق تحرّك منذ ذلك الحين. إعادة القياس تُظهر أثر ما نفّذته وتصحح الاتجاه.',
            ];
        }

        // 6) لا تقارير إطلاقًا: المشروع واقف قبل خط البداية.
        if ($latestReport === null) {
            $items[] = [
                'type' => 'start',
                'title' => 'مشروعك لم يُقس بعد',
                'body' => 'شغّل تشخيص الجاهزية لتحصل على درجة وخطة — كل ما بعده يُبنى عليه.',
            ];
        }

        // 7) هدوء تام ليس فراغًا: نعطي اتجاهًا لا صمتًا.
        if ($items === []) {
            $items[] = [
                'type' => 'steady',
                'title' => 'أسبوع هادئ — لا متأخرات ولا تغييرات',
                'body' => 'الوقت المناسب لخطوة تقدم بدل خطوة إصلاح.',
            ];
        }

        return $items;
    }

    /**
     * خطوة الأسبوع: أعلى إشارة فعلية، وإلا فالأداة التالية في الرحلة.
     *
     * @param  array<int, array{type: string, title: string, body: string}>  $items
     * @return array{title: string, description: string, url: string|null}|null
     */
    private function nextStep(Project $project, array $items): ?array
    {
        $types = array_column($items, 'type');

        if (in_array('overdue', $types, true)) {
            return [
                'title' => 'أنجز مهمة متأخرة واحدة اليوم',
                'description' => 'ابدأ بالأقدم — إغلاق واحدة يكسر الجمود ويعيد القائمة إلى حجم قابل للإدارة.',
                'url' => route('app.projects.tasks', $project),
            ];
        }

        if (in_array('drift', $types, true) || in_array('stale', $types, true)) {
            return [
                'title' => 'أعد التحليل ببياناتك المحدّثة',
                'description' => 'إجاباتك السابقة محفوظة — إعادة التشغيل تأخذ دقائق وتعطيك درجة تعكس واقعك الحالي.',
                'url' => route('app.projects.show', $project),
            ];
        }

        $suggestion = $this->suggester->suggest($project);

        if ($suggestion !== null) {
            return [
                'title' => 'شغّل «'.$suggestion['tool']->title.'»',
                'description' => $suggestion['reason'],
                'url' => route('app.tools.show', $suggestion['tool']),
            ];
        }

        return null;
    }
}
