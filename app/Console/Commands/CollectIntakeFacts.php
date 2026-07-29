<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Modules\Intake\IntakeCollector;
use App\Modules\Intake\IntakeFactMap;
use Illuminate\Console\Command;

/**
 * تمرير الجامع على كل المشاريع.
 *
 * ليس `backfill` لبيانات تجريبية (§٢ بند ٧): لا يخترع شيئًا ولا يُقدِّر، بل
 * يعيد قراءة إجابات موجودة بمفاتيح المحاور. لزومه أن `IntakeFactMap` بيان
 * يتغيّر — إضافة مصدر جديد لمفتاح لا تعني شيئًا حتى يُقرأ على ما هو مخزَّن.
 */
class CollectIntakeFacts extends Command
{
    protected $signature = 'brain:collect
        {--project= : معرّف مشروع واحد بدل كل المشاريع}';

    protected $description = 'قراءة إجابات المشاريع بمفاتيح المحاور وكتابتها في الدماغ';

    public function handle(IntakeCollector $collector): int
    {
        $query = Project::query()->with(['profile', 'audiences', 'competitors']);

        if ($id = $this->option('project')) {
            $query->whereKey($id);
        }

        $projects = 0;
        $facts = 0;

        $query->chunkById(50, function ($chunk) use ($collector, &$projects, &$facts): void {
            foreach ($chunk as $project) {
                $written = $collector->collect($project);

                $projects++;
                $facts += count($written);

                $this->line(sprintf(
                    '  %s — %d من %d مفتاحًا',
                    $project->name,
                    count($written),
                    count(IntakeFactMap::all()),
                ));
            }
        });

        if ($projects === 0) {
            $this->warn('لا مشاريع مطابقة.');

            return self::SUCCESS;
        }

        $this->info("تمّت قراءة {$projects} مشروعًا · {$facts} حقيقة سارية.");

        return self::SUCCESS;
    }
}
