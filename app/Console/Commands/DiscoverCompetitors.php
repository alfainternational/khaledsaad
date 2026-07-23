<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Competitors\CompetitorDiscovery;
use Illuminate\Console\Command;

/**
 * اكتشاف مرشّحي المنافسين الإقليميين للمشاريع النشطة.
 *
 * يُشغَّل مجدولًا لا داخل الطلب: النداء الخارجي لا يُبطئ المستخدم، والمرشّحون
 * يكونون جاهزين في تقريره القادم بانتظار تأكيده.
 */
class DiscoverCompetitors extends Command
{
    protected $signature = 'competitors:discover {--project= : سلاق مشروع بعينه}';

    protected $description = 'اقتراح منافسين إقليميين مرشّحين من نتائج البحث (يحتاج مصدرًا حيًّا مفعّلًا)';

    public function handle(CompetitorDiscovery $discovery): int
    {
        if (! $discovery->isAvailable()) {
            $this->warn('مصدر اكتشاف المنافسين غير مفعّل. المنافسون الذين سمّاهم المستخدم يعملون كما هم.');
            $this->line('فعّله بضبط BENCHMARKS_LIVE_ENABLED ومفاتيح المزوّد في .env');

            return self::SUCCESS;
        }

        $projects = Project::query()
            ->when($this->option('project'), fn ($query, $slug) => $query->where('slug', $slug))
            ->with('profile')
            ->get();

        $total = 0;

        foreach ($projects as $project) {
            $count = $discovery->discoverFor($project);
            $total += $count;
            $this->line("- {$project->name}: {$count} مرشّح جديد");
        }

        $this->info("اكتمل. {$total} مرشّح عبر {$projects->count()} مشروع، بانتظار تأكيد أصحابها.");

        return self::SUCCESS;
    }
}
