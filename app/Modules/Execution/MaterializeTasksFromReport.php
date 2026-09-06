<?php

declare(strict_types=1);

namespace App\Modules\Execution;

use App\Models\Report;
use App\Models\Task;
use App\Modules\Insights\FunnelRecorder;
use Illuminate\Support\Facades\Log;

/**
 * تحويل توصيات التقرير إلى مهام — تلقائيًّا عند النشر.
 *
 * **أهم تغيير منتَجي في هذه الدفعة.** كان المنتج ينتهي عند النقطة التي
 * تبدأ عندها القيمة: ستة تقارير مليئة بالتوصيات، وصفر مهام. زرُّ «حوّلها
 * إلى مهمة» يفترض أن المستخدم يعرف أنه يحتاجها — وهو جاء ليخبره أنت.
 *
 * الحالة الابتدائية `suggested` لا `todo`: الخطة تُقترح كاملة ويتبنّى
 * منها ما يريد. إغراقُه بأربع عشرة مهمة مفتوحة يُنتج الهجر نفسه الذي
 * ينتجه صفرُ مهام، من الطرف الآخر.
 *
 * والبنية موجودة أصلًا في كل توصية: الناتج وتعريف الإنجاز وأول خمس دقائق
 * والمؤشر والأثر والجهد. لم ينقص إلا حقلا الحالة والمالك.
 */
final class MaterializeTasksFromReport
{
    public function handle(Report $report): int
    {
        // التقرير غير المنشور لا يُنتج خطة: توصياته قد تتغير عند إعادة
        // التحقق، فمهامٌ مبنيّة عليها تصير خطةً لتقرير لم يُصدر.
        if ($report->status !== 'published') {
            return 0;
        }

        $created = 0;

        $recommendations = $report->recommendations()
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        foreach ($recommendations as $recommendation) {
            $dueInDays = $this->dueInDays($recommendation->effort);

            // `firstOrCreate` على معرّف التوصية: إعادة نشر التقرير نفسه
            // لا تُنشئ مهامًا ثانية، ولا تُعيد ما أنجزه المستخدم أو أسقطه
            // إلى «مقترحة» من جديد (INV-9).
            $task = Task::firstOrCreate(
                ['recommendation_id' => $recommendation->id],
                [
                    'project_id' => $report->project_id,
                    'title' => $recommendation->title,
                    'description' => $recommendation->description,
                    'steps' => $recommendation->action_steps ?: null,
                    'worked_example' => $recommendation->worked_example,
                    'timeframe' => $recommendation->timeframe,
                    'status' => Task::STATUS_SUGGESTED,
                    'priority' => $recommendation->priority,
                    'impact' => $recommendation->impact,
                    'effort' => $recommendation->effort,
                    'due_date' => now()->addDays($dueInDays),
                    'reminder_at' => now()->addDays(max(1, (int) floor($dueInDays * 2 / 3))),
                ],
            );

            if ($task->wasRecentlyCreated) {
                $created++;
            }
        }

        if ($created > 0) {
            // حدث قمع (§13): يقيس إقفال الحلقة — كم تقريرًا صار خطة.
            Log::info('وُلّدت مهام من تقرير منشور', [
                'event' => FunnelRecorder::TASK_MATERIALIZED,
                'report_id' => $report->id,
                'tasks' => $created,
            ]);
        }

        return $created;
    }

    /**
     * المدة من الجهد: الجهد البسيط يُنجز في أسبوع، والثقيل يحتاج شهرًا
     * ونصفًا. موعدٌ واحد لكل المهام يجعل نصفها متأخرًا بلا ذنب.
     */
    private function dueInDays(?string $effort): int
    {
        return match ($effort) {
            'low' => 7,
            'high' => 45,
            default => 21,
        };
    }
}
