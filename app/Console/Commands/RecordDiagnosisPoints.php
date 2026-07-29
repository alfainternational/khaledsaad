<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Modules\Diagnosis\MaturityAggregator;
use App\Modules\Diagnosis\ScoreHistory;
use App\Modules\Shared\Metrics\MetricKey;
use Illuminate\Console\Command;

/**
 * تقييد النقطة الدورية لكل نشاط مقيس.
 *
 * أمر مستقل عن `growth:watch` عن قصد. الفحص الحي يمرّ على **المراقبين
 * النشطين** وحدهم، وتعليق السلسلة الزمنية به كان يعني أن نشاطًا لم يفعّل
 * تقريرًا حيًّا لا تُقيَّد له نقطة واحدة أبدًا: لا تاريخ، ولا اتجاه، ولوحة
 * الوكالة تعرض «قياس واحد فقط» إلى الأبد.
 *
 * السلسلة الزمنية خاصيّة النشاط لا خاصيّة اشتراكه في المراقبة (§١١ مرحلة ٢).
 *
 * حتمي بالكامل وبلا تكلفة: يقرأ الدماغ ويحسب. `isDueForPoint` يمنع أكثر من
 * نقطة كل سبعة أيام، فتشغيله يوميًّا آمن — وأربع نقاط بهذا الفاصل هي نافذة
 * الأربعة أسابيع التي يشترطها §٤.٢.
 */
class RecordDiagnosisPoints extends Command
{
    protected $signature = 'diagnosis:record
        {--project= : معرّف نشاط واحد بدل الجميع}
        {--force : تجاهل فاصل الأسبوع}';

    protected $description = 'تقييد نقطة درجة النضج الدورية لكل نشاط مقيس';

    public function handle(MaturityAggregator $maturity, ScoreHistory $history): int
    {
        $query = Project::query()->with(['profile']);

        if ($id = $this->option('project')) {
            $query->whereKey($id);
        }

        $recorded = 0;
        $skipped = 0;

        $query->chunkById(50, function ($chunk) use ($maturity, $history, &$recorded, &$skipped): void {
            foreach ($chunk as $project) {
                if (! $this->option('force') && ! $history->isDueForPoint($project)) {
                    $skipped++;

                    continue;
                }

                /*
                 * النشاط غير المقيس لا تُقيَّد له نقطة: سلسلةٌ من أصفار تصنع
                 * «اتجاهًا ثابتًا» لنشاط لم يُقَس قط، ثم تقفز عند أول قياس
                 * فتُقرأ تحسّنًا هائلًا لم يحدث.
                 */
                $result = $maturity->compute($project);

                if (($result['axes_active'] ?? 0) === 0) {
                    $skipped++;

                    continue;
                }

                $snapshot = $maturity->computeAndSnapshot($project);
                $recorded++;

                $this->line(sprintf(
                    '  %s — %d/100 من %d محاور',
                    $project->name,
                    $snapshot[MetricKey::MATURITY_SCORE],
                    $snapshot['axes_active'],
                ));
            }
        });

        $this->info("قُيِّدت {$recorded} نقطة · تُخطّي {$skipped} نشاطًا.");

        return self::SUCCESS;
    }
}
