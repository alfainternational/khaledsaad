<?php

namespace App\Console\Commands;

use App\Models\ReportWatcher;
use App\Modules\Alerts\LiveReportChecker;
use App\Notifications\LiveReportChangedNotification;
use Illuminate\Console\Command;

/**
 * الفحص اليومي للتقارير الحيّة: حتمي وصامت ورخيص — لا استدعاء نموذج،
 * فقط مقارنة لقطة التقرير بحالة المشروع اليوم.
 */
class WatchLiveReports extends Command
{
    protected $signature = 'growth:watch';

    protected $description = 'فحص التقارير الحيّة وتنبيه أصحابها عند تغيّر مدخلاتها';

    public function handle(LiveReportChecker $checker): int
    {
        if (! config('growth.watch_enabled', true)) {
            $this->warn('التقرير الحي معطّل من الإعدادات.');

            return self::SUCCESS;
        }

        $notified = 0;
        $checked = 0;

        ReportWatcher::where('status', ReportWatcher::STATUS_ACTIVE)
            ->with(['report.toolRun.toolVersion', 'project.profile', 'user'])
            ->chunkById(50, function ($watchers) use ($checker, &$notified, &$checked): void {
                foreach ($watchers as $watcher) {
                    $checked++;
                    $changes = $checker->check($watcher);
                    $fingerprint = $watcher->project !== null
                        ? $checker->fingerprint($watcher->project)
                        : $watcher->baseline_fingerprint;

                    $watcher->last_checked_at = now();

                    // التنبيه عن التغيّر بعد التفعيل فقط (البصمة اختلفت عن بصمة
                    // يوم التفعيل)، ومرة واحدة لكل حالة (بصمة أُشعر عنها لا تُعاد).
                    if ($changes !== []
                        && $fingerprint !== $watcher->baseline_fingerprint
                        && $fingerprint !== $watcher->notified_fingerprint) {
                        $watcher->changes = $changes;
                        $watcher->last_changed_at = now();
                        $watcher->notified_fingerprint = $fingerprint;

                        $watcher->user?->notify(
                            new LiveReportChangedNotification($watcher->report, $changes),
                        );
                        $notified++;
                    }

                    // عاد المشروع لمطابقة التقرير: صفّر حالة التغيير.
                    if ($changes === []) {
                        $watcher->changes = null;
                    }

                    $watcher->save();
                }
            });

        $this->info("فُحص {$checked} تقريرًا حيًّا، ونُبّه أصحاب {$notified} منها.");

        return self::SUCCESS;
    }
}
