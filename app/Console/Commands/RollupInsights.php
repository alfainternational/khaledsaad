<?php

namespace App\Console\Commands;

use App\Modules\Insights\InsightsRollup;
use Illuminate\Console\Command;

class RollupInsights extends Command
{
    protected $signature = 'insights:rollup
        {--days=2 : عدد الأيام الأخيرة التي يُعاد تجميعها}
        {--from= : تاريخ بداية صريح (Y-m-d) لإعادة بناء أثر رجعي}';

    protected $description = 'تجميع إحصاءات الزوّار اليومية من الصفوف الخام';

    public function handle(InsightsRollup $rollup): int
    {
        $from = $this->option('from');
        $days = max(1, (int) $this->option('days'));

        $written = $from !== null
            ? $rollup->rebuildSince($from)
            : $rollup->rebuildLastDays($days);

        $this->info("تم تجميع {$written} صفًّا.");

        return self::SUCCESS;
    }
}
