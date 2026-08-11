<?php

namespace App\Console\Commands;

use App\Modules\Insights\InsightsRollup;
use Illuminate\Console\Command;

class PruneInsights extends Command
{
    protected $signature = 'insights:prune {--days= : تجاوز مدة الاحتفاظ المضبوطة}';

    protected $description = 'حذف صفوف الزوّار الخام بعد مدة الاحتفاظ، مع إبقاء التجميع اليومي';

    public function handle(InsightsRollup $rollup): int
    {
        $days = (int) ($this->option('days') ?? config('insights.retention_days', 400));

        if ($days <= 0) {
            $this->comment('الاحتفاظ غير محدود — لا حذف.');

            return self::SUCCESS;
        }

        /*
         * التجميع قبل الحذف لا بعده.
         *
         * الترتيب المعكوس يمحو الصفوف الخام لأيام لم تُجمَّع بعد، فتختفي
         * من التاريخ نهائيًّا بلا أثر — وهي خسارة لا تُسترجع.
         */
        $rollup->rebuildLastDays(3);

        $deleted = $rollup->prune($days);

        $this->info("حُذفت {$deleted} زيارة أقدم من {$days} يومًا. التجميع اليومي باقٍ.");

        return self::SUCCESS;
    }
}
