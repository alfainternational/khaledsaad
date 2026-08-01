<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Notifications\TaskOverdueNotification;
use App\Notifications\TaskReminderNotification;
use Illuminate\Console\Command;

/**
 * تنبيهات المهام: تذكير قبل الموعد، وإشعار عند التأخر.
 *
 * حتمي ورخيص — لا استدعاء نموذج، فقط قراءة تواريخ. سبب وجوده أن
 * `TaskOverdueNotification` كان مكتوبًا منذ بناء المهام ولا يطلقه أحد،
 * فالمهمة تتأخر بصمت ويكتشفها صاحبها حين يفتح اللوحة — إن فتحها.
 *
 * التنبيه هو المخرج المتكرر الوحيد في المنتج (§٦)؛ مهمة بلا تنبيه تعود
 * ملفًّا ميتًا في لوحة، وهو ما يمنعه §٤.٥.
 *
 * `reminded_at` و`completed_at` يمنعان التكرار: لا يُرسَل تذكير مرتين
 * لنفس المهمة، ولا يُلاحَق أحدٌ على مهمة أنجزها.
 */
class RemindTasks extends Command
{
    protected $signature = 'tasks:remind';

    protected $description = 'تذكير أصحاب المهام قبل موعدها، وتنبيههم عند تأخرها';

    public function handle(): int
    {
        $reminded = $this->remind();
        $overdue = $this->overdue();

        $this->info("تذكيرات: {$reminded} — تنبيهات تأخر: {$overdue}");

        return self::SUCCESS;
    }

    /**
     * التذكير المبكر: عند بلوغ `reminder_at` وقبل الموعد.
     */
    private function remind(): int
    {
        $sent = 0;

        Task::query()
            ->whereNotNull('reminder_at')
            ->whereNull('reminded_at')
            ->where('reminder_at', '<=', now())
            ->where('status', '!=', Task::STATUS_DONE)
            ->with(['owner', 'project'])
            ->chunkById(100, function ($tasks) use (&$sent): void {
                foreach ($tasks as $task) {
                    // الختم يُكتب دائمًا حتى مع غياب المالك: مهمة بلا مالك
                    // لا يجوز أن تُفحص كل يوم إلى الأبد.
                    $task->forceFill(['reminded_at' => now()])->save();

                    if ($task->owner === null || $task->project === null) {
                        continue;
                    }

                    $task->owner->notify(new TaskReminderNotification($task));
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * التأخر: بعد الموعد ولم تُنجَز.
     *
     * يُرسَل مرة واحدة لكل مهمة، ويُعلَّم بإعادة ضبط `reminded_at` على الآن
     * بعد نقل `reminder_at` إلى null — فالمهمة المتأخرة لا تحتاج تذكيرًا
     * مسبقًا بعد اليوم، وإنما قرارًا من صاحبها.
     */
    private function overdue(): int
    {
        $sent = 0;

        Task::query()
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->where('status', '!=', Task::STATUS_DONE)
            ->whereNotNull('reminder_at')
            ->with(['owner', 'project'])
            ->chunkById(100, function ($tasks) use (&$sent): void {
                foreach ($tasks as $task) {
                    $task->forceFill(['reminder_at' => null])->save();

                    if ($task->owner === null || $task->project === null) {
                        continue;
                    }

                    $task->owner->notify(new TaskOverdueNotification($task));
                    $sent++;
                }
            });

        return $sent;
    }
}
