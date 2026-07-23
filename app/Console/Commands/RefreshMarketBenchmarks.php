<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Tools\LiveMarketBenchmarks;
use Illuminate\Console\Command;

/**
 * تحديث أرقام السوق الحيّة للمجالات التي يعمل فيها مستخدمونا فعلًا.
 *
 * يُشغَّل مجدولًا لا داخل الطلب: صاحب المشروع لا ينتظر واجهة خارجية
 * وهو يملأ خانة، والرقم يكون جاهزًا قبل أن يسأل عنه.
 */
class RefreshMarketBenchmarks extends Command
{
    protected $signature = 'benchmarks:refresh';

    protected $description = 'جلب أرقام السوق الحيّة (تكلفة النقرة والعميل) للمجالات المستخدمة';

    public function handle(LiveMarketBenchmarks $live): int
    {
        if (! $live->isAvailable()) {
            $this->warn('المصدر الحيّ غير مفعّل. الأرقام الإرشادية تعمل كما هي.');
            $this->line('فعّله بضبط BENCHMARKS_LIVE_ENABLED ومفاتيح المزوّد في .env');

            return self::SUCCESS;
        }

        // نطلب فقط للتوليفات المستخدمة فعلًا: لا ننفق نداءات على سوق لا أحد فيه.
        $scopes = Project::with('profile')
            ->get()
            ->map(fn (Project $project) => [
                'industry' => $project->industry,
                'geography' => $project->profile?->geography,
                'business_model' => $project->profile?->business_model,
            ])
            ->unique(fn (array $scope) => implode('|', $scope))
            ->values();

        $written = 0;

        foreach ($scopes as $scope) {
            foreach (['cost_per_customer', 'cost_per_click'] as $metric) {
                $snapshot = $live->refresh($metric, $scope['industry'], $scope['geography'], $scope['business_model']);

                if ($snapshot !== null) {
                    $written++;
                }
            }
        }

        $this->info("حُدِّث {$written} رقمًا من السوق عبر {$scopes->count()} مجالًا.");

        return self::SUCCESS;
    }
}
