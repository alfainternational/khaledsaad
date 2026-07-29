<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Modules\AiReadiness\Jobs\AuditProjectSite;
use Illuminate\Console\Command;

/**
 * إعادة تدقيق مواقع الأنشطة دوريًّا.
 *
 * بلا هذا الأمر كانت `AuditProjectSite` مبنيةً ولا تُرسَل من أي مكان: التدقيق
 * لا يقع إلا حين يضغط صاحب النشاط الزر بنفسه. أثره أن المحور السابع يتجمّد
 * على قياس واحد قديم — فلا تتحرّك درجته، ولا يُنتج تغيّرًا، ولا يصل تنبيه.
 * والتنبيه هو المخرج المتكرر الوحيد الذي يقوم عليه الاشتراك (§٨).
 *
 * أسبوعي لا يومي: بنية موقع لا تتغيّر بين يوم وآخر، وجلب صفحات كل نشاط
 * يوميًّا ضغطٌ على مواقع العملاء بلا معلومة جديدة.
 *
 * بلا تكلفة نموذج: التدقيق قارئ HTTP حتمي، فلا يمسّ سقف الاستعلامات.
 */
class RefreshSiteAudits extends Command
{
    protected $signature = 'readiness:refresh
        {--project= : معرّف نشاط واحد بدل الجميع}';

    protected $description = 'إعادة تدقيق مواقع الأنشطة في الطابور';

    public function handle(): int
    {
        $query = Project::query()
            ->with('profile')
            ->whereHas('profile', fn ($q) => $q->whereNotNull('website')->where('website', '!=', ''));

        if ($id = $this->option('project')) {
            $query->whereKey($id);
        }

        $queued = 0;

        $query->chunkById(50, function ($chunk) use (&$queued): void {
            foreach ($chunk as $project) {
                $url = $project->profile?->website;

                if (blank($url)) {
                    continue;
                }

                AuditProjectSite::dispatch($project->id, $url);
                $queued++;
            }
        });

        // نشاط بلا موقع ليس فشلًا: المحور السابع لا ينطبق عليه بعد.
        $this->info("أُدرج {$queued} تدقيقًا في الطابور.");

        return self::SUCCESS;
    }
}
