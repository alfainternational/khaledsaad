<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Notifications\WeeklyPulseNotification;
use App\Services\Growth\PulseComposer;
use Illuminate\Console\Command;

/**
 * تأليف نبض الأسبوع لكل مساحة عمل لها مالك ومشاريع، وإشعار المالك مرة
 * واحدة مهما تعددت مشاريعه.
 */
class ComposeWeeklyPulse extends Command
{
    protected $signature = 'growth:pulse';

    protected $description = 'تأليف النبض الأسبوعي لكل المشاريع وإشعار أصحابها';

    public function handle(PulseComposer $composer): int
    {
        if (! config('growth.pulse_enabled', true)) {
            $this->warn('النبض الأسبوعي معطّل من الإعدادات.');

            return self::SUCCESS;
        }

        $weekStart = now()->startOfWeek();
        $digests = 0;
        $notifiedOwners = 0;

        Workspace::whereNotNull('owner_id')
            ->whereHas('projects')
            ->with(['owner', 'projects'])
            ->chunkById(25, function ($workspaces) use ($composer, $weekStart, &$digests, &$notifiedOwners): void {
                foreach ($workspaces as $workspace) {
                    $highlights = [];

                    foreach ($workspace->projects as $project) {
                        $digest = $composer->compose($project, $weekStart);
                        $digests++;

                        $first = $digest->items[0] ?? null;

                        if ($first !== null) {
                            $highlights[] = $project->name.': '.$first['title'];
                        }
                    }

                    if ($highlights !== [] && $workspace->owner !== null) {
                        $workspace->owner->notify(new WeeklyPulseNotification(
                            $workspace->projects->count(),
                            $highlights,
                        ));
                        $notifiedOwners++;
                    }
                }
            });

        $this->info("أُلّف {$digests} نبضًا، وأُشعر {$notifiedOwners} مالكًا.");

        return self::SUCCESS;
    }
}
