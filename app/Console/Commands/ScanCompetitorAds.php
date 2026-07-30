<?php

namespace App\Console\Commands;

use App\Contracts\AdLibraryProvider;
use App\Models\Project;
use App\Modules\Competitors\AdLibraries\AdLibraryScan;
use Illuminate\Console\Command;

/**
 * سحب مكتبات الإعلانات لمنافسي المشاريع النشطة (المرحلة ٤).
 *
 * مجدول لا داخل الطلب (§٨): السحب الخارجي لا يُبطئ المستخدم. ويُبنى على حدّ
 * الأمانة (§١٠): بلا مزوّد سحب مضبوط لا يكتب لقطات وهمية — يُعلن أن السحب لم
 * يُفعَّل ويترك الروابط الرسمية للفحص اليدوي، كما يفعل أمر اكتشاف المنافسين.
 */
class ScanCompetitorAds extends Command
{
    protected $signature = 'competitors:scan-ads {--project= : سلاق مشروع بعينه}';

    protected $description = 'سحب مكتبات إعلانات المنافسين (يحتاج مزوّد سحب مفعّلًا)';

    public function handle(AdLibraryProvider $provider, AdLibraryScan $scan): int
    {
        if (! $provider->isAvailable()) {
            $this->warn('لم يُفعَّل مزوّد سحب مكتبات الإعلانات بعد.');
            $this->line('الروابط الرسمية لمكتبات الإعلانات متاحة في شاشة المنافسين للفحص اليدوي.');

            return self::SUCCESS;
        }

        $projects = Project::query()
            ->when($this->option('project'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        foreach ($projects as $project) {
            $summary = $scan->forProject($project);
            $this->line(
                "- {$project->name}: {$summary['fetched']} مرصود · "
                ."{$summary['broke']} متكسّر · {$summary['unavailable']} غير متاح",
            );
        }

        $this->info("اكتمل عبر {$projects->count()} مشروع.");

        return self::SUCCESS;
    }
}
