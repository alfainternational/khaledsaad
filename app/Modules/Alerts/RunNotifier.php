<?php

namespace App\Modules\Alerts;

use App\Models\ToolRun;
use App\Models\User;
use App\Notifications\AnalysisFailedNotification;
use App\Notifications\LowCreditsNotification;
use App\Notifications\ReportReadyNotification;

/**
 * نقطة واحدة تربط أحداث التشغيل بالإشعارات.
 *
 * وجودها يمنع تناثر منطق «من يُشعَر ومتى» داخل خط الأنابيب، ويسمح بكتم
 * الإشعارات في الاختبارات عبر Notification::fake دون تعديل الخط.
 */
class RunNotifier
{
    /**
     * الحد الذي ينبّه عنده على انخفاض الرصيد.
     * أقل من منحة الخطة المجانية (5) حتى لا يُنبَّه المستخدم الجديد قبل أن يصرف.
     */
    private const LOW_CREDITS_THRESHOLD = 3;

    public function reportReady(ToolRun $run): void
    {
        $user = $this->owner($run);
        $report = $run->report;

        if ($user === null || $report === null) {
            return;
        }

        $user->notify(new ReportReadyNotification($report));

        $this->maybeWarnLowCredits($run, $user);
    }

    public function reportFailed(ToolRun $run): void
    {
        $this->owner($run)?->notify(new AnalysisFailedNotification($run));
    }

    private function maybeWarnLowCredits(ToolRun $run, User $user): void
    {
        $wallet = $run->project->workspace->wallet;

        if ($wallet !== null && $wallet->balance > 0 && $wallet->balance <= self::LOW_CREDITS_THRESHOLD) {
            $user->notify(new LowCreditsNotification($wallet->balance));
        }
    }

    /**
     * مالك مساحة العمل هو المُشعَر. تشغيلات الضيف بلا مالك فلا إشعار.
     */
    private function owner(ToolRun $run): ?User
    {
        return $run->project->workspace->owner;
    }
}
