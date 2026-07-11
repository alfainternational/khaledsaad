<?php

use App\Application\Intelligence\CompileWorkspaceIntelligenceAction;
use App\Contracts\AiGatewayInterface;
use App\Domain\AI\Kernel\Agents\Ops\AnomalyDetector;
use App\Domain\AI\Kernel\Knowledge\KnowledgeStore;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\Tool;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Jobs\CaptureMonitoringSnapshotJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('intelligence:monitoring-snapshots {cadence=weekly}', function (string $cadence): void {
    Project::query()
        ->where('monitoring_enabled', true)
        ->orderBy('id')
        ->chunkById(100, function ($projects): void {
            foreach ($projects as $project) {
                CaptureMonitoringSnapshotJob::dispatch($project->id);
            }
        });

    $this->info(sprintf('Queued monitoring snapshots for cadence: %s', $cadence));
})->purpose('Queue monitoring snapshots for projects with intelligence monitoring enabled.');

/*
 | التعلّم الدائم للعقل المحلي: يمسح كل عمليات الأدوات الفعلية، يستخرج أنماطاً
 | (معدل إكمال كل أداة، أكثر أداة توقّفاً عندها، متوسط الجودة)، ويكتبها في
 | KnowledgeStore لتُغذّي محرّكي الاستدلال والتنبؤ. cron-only، بلا worker دائم.
 */
Artisan::command('ai:learn', function (KnowledgeStore $knowledge): void {
    $rows = ToolRun::query()
        ->selectRaw('tool_code, COUNT(*) as runs, COUNT(DISTINCT project_id) as projects, AVG(completeness_score) as avg_quality')
        ->groupBy('tool_code')
        ->get();

    if ($rows->isEmpty()) {
        $this->info('ai:learn — لا توجد بيانات استخدام بعد.');

        return;
    }

    $totalProjects = (int) ToolRun::query()->distinct()->count('project_id');

    $perTool = $rows->map(fn ($r): array => [
        'tool_code' => (string) $r->tool_code,
        'runs' => (int) $r->runs,
        'projects' => (int) $r->projects,
        'avg_quality' => round((float) $r->avg_quality, 1),
        'completion_rate' => $totalProjects > 0 ? round((int) $r->projects / $totalProjects, 2) : 0.0,
    ])->values()->all();

    // أكثر أداة توقّفاً عندها = أدنى معدل إكمال بين الأدوات التي بدأها أحد.
    $dropOff = collect($perTool)->sortBy('completion_rate')->first();

    $knowledge->remember('patterns.global', [
        'total_projects' => $totalProjects,
        'tools' => $perTool,
        'common_drop_off_tool' => $dropOff['tool_code'] ?? null,
        'sample_size' => (int) $rows->sum('runs'),
    ]);

    $this->info(sprintf('ai:learn — تعلّم من %d عملية عبر %d أداة.', (int) $rows->sum('runs'), $rows->count()));
})->purpose('بناء المعرفة الذاتية للعقل من الاستخدام الفعلي (التعلّم الدائم).');

Schedule::command('ai:learn')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->name('ai-continuous-learning');

if ((bool) config('services.knowledge.project_sync', false)) {
    Schedule::command('knowledge:sync-projects')
        ->dailyAt('03:15')
        ->withoutOverlapping()
        ->name('knowledge-project-sync');
}

if ((bool) config('services.knowledge.upload_processing', false)) {
    Schedule::command('knowledge:process-uploads --limit=20')
        ->everyTenMinutes()
        ->withoutOverlapping()
        ->name('knowledge-upload-processing');
}

/*
 | Compile-Ahead: يعيد تجميع artifact الذكاء لكل المساحات (ملء أوّلي + شبكة أمان).
 | العرض اليومي يقرأ الناتج الجاهز فقط — صفر حساب وقت الطلب.
 */
Artisan::command('ai:compile {workspace?}', function (CompileWorkspaceIntelligenceAction $compiler, ?string $workspace = null): void {
    $query = Workspace::query()->when($workspace !== null, fn ($q) => $q->whereKey($workspace));
    $count = 0;

    $query->orderBy('id')->chunkById(100, function ($workspaces) use ($compiler, &$count): void {
        foreach ($workspaces as $ws) {
            $compiler->handle($ws);
            $count++;
        }
    });

    $this->info(sprintf('ai:compile — جُمِّع الذكاء لـ %d مساحة عمل.', $count));
})->purpose('تجميع artifact الذكاء المسبق لكل المساحات (Compile-Ahead).');

Schedule::command('ai:compile')
    ->dailyAt('03:45')
    ->withoutOverlapping()
    ->name('ai-intelligence-compile');

/*
 | Distillation: تقطير خبرة LLM إلى playbooks تسويقية محلية في وقت الفراغ (cron).
 | يُحسب مرة (مكلف) ويُخدَم محلياً للأبد (مجاني وقت الطلب) — مبدأ "اصنع الذكاء مسبقاً".
 */
Artisan::command('ai:distill {limit=8}', function (AiGatewayInterface $gateway, KnowledgeStore $knowledge, int $limit = 8): void {
    if ((bool) config('services.ai.kill_switch', false)) {
        $this->warn('ai:distill — متوقف (Kill Switch مفعّل).');

        return;
    }
    if (! config('services.gemini.key') && ! config('services.nvidia.key')) {
        $this->warn('ai:distill — لا مزوّد LLM مهيّأ.');

        return;
    }

    $system = 'أنت خبير تسويق استراتيجي بخبرة 15 سنة. تُخرج معرفة مكثّفة وعملية بالعربية، بصيغة JSON صالح فقط بلا أي نص حوله.';
    $done = 0;
    $fresh = now()->subDays(30);

    foreach (Tool::query()->orderBy('stage')->get() as $tool) {
        if ($done >= (int) $limit) {
            break;
        }

        $existing = $knowledge->recall('playbook.'.$tool->code);
        if (is_array($existing) && ! empty($existing['learned_at']) && strtotime((string) $existing['learned_at']) > $fresh->getTimestamp()) {
            continue;
        }

        $prompt = 'الأداة التسويقية: "'.($tool->name ?: $tool->code).'" (الكود: '.$tool->code.').'."\n"
            .'أعد JSON بهذا الشكل فقط:'."\n"
            .'{"principles":["مبدأ عملي 1","مبدأ 2","مبدأ 3"],"common_mistakes":["خطأ شائع 1","خطأ 2"],"quick_win":"أسرع مكسب عملي","key_metric":"المؤشر الأهم للنجاح"}';

        $text = $gateway->generateText($prompt, $system);
        if (! $text) {
            continue;
        }

        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
        $parsed = json_decode((string) $clean, true);
        if (! is_array($parsed) || empty($parsed['principles'])) {
            continue;
        }

        $knowledge->remember('playbook.'.$tool->code, [
            'tool' => $tool->code,
            'tool_name' => $tool->name,
            'principles' => array_slice((array) ($parsed['principles'] ?? []), 0, 5),
            'common_mistakes' => array_slice((array) ($parsed['common_mistakes'] ?? []), 0, 4),
            'quick_win' => (string) ($parsed['quick_win'] ?? ''),
            'key_metric' => (string) ($parsed['key_metric'] ?? ''),
        ]);
        $done++;
    }

    $this->info(sprintf('ai:distill — قُطِّرت %d playbook تسويقية.', $done));
})->purpose('تقطير معرفة تسويقية من LLM إلى المخزن المحلي (Distillation).');

Schedule::command('ai:distill')
    ->weeklyOn(2, '04:15')
    ->withoutOverlapping()
    ->name('ai-knowledge-distillation');

/*
 | Teacher Loop: النظام المحلي يُنتج، وLLM يُقيّم مخرجاته في الخلفية ويستخرج دروساً
 | عامة قابلة لإعادة الاستخدام يخزّنها (teach.{tool}) ليتعلّم منها النظام المحلي
 | فيحسّن مخرجاته لاحقاً بلا تدخّل — جوهر «النموذج الكبير يعلّم الصغير».
 */
Artisan::command('ai:teach {limit=6}', function (AiGatewayInterface $gateway, KnowledgeStore $knowledge, int $limit = 6): void {
    if ((bool) config('services.ai.kill_switch', false)) {
        $this->warn('ai:teach — متوقف (Kill Switch).');

        return;
    }
    if (! config('services.gemini.key') && ! config('services.nvidia.key')) {
        $this->warn('ai:teach — لا مزوّد LLM مهيّأ.');

        return;
    }

    $toolCodes = ToolRun::query()->select('tool_code')->groupBy('tool_code')->pluck('tool_code');
    $taught = 0;

    foreach ($toolCodes as $code) {
        if ($taught >= (int) $limit) {
            break;
        }

        $runs = ToolRun::query()->where('tool_code', $code)->orderByDesc('id')->limit(4)->get();
        $samples = [];
        foreach ($runs as $run) {
            $inputs = collect($run->inputs_json ?? [])
                ->filter(fn ($v) => is_string($v) && trim($v) !== '' && $v !== 'brief')
                ->map(fn ($v, $k) => $k.': '.trim((string) $v))
                ->take(6)->implode('؛ ');
            $headline = (string) data_get($run->summary_json, 'headline', '');
            $bullets = implode(' • ', array_map('strval', (array) data_get($run->summary_json, 'bullets', [])));
            if (trim($inputs) === '') {
                continue;
            }
            $samples[] = 'مدخلات المستخدم: '.$inputs."\nمخرج النظام المحلي: ".trim($headline.' | '.$bullets);
        }

        if ($samples === []) {
            continue;
        }

        $prompt = implode("\n", [
            'أنت معلّم خبير في التسويق. النظام المحلي (قائم على قواعد) أنتج هذه المخرجات لأداة «'.$code.'».',
            'استخرج دروساً عامة محدّدة (وليست عبارات إنشائية) تجعل مخرجاته أدقّ وأكثر مطابقة لنيّة كل حقل.',
            '',
            implode("\n---\n", array_slice($samples, 0, 4)),
            '',
            'مثال على درس جيد: «اربط كل توصية بنتيجة قابلة للقياس (رقم/زمن) بدل وصف عام» أو «لا تكرّر إجابة حقل في حقل آخر».',
            'ممنوع نسخ كلمات هذه التعليمات أو كتابة عبارات نائبة مثل «قاعدة عامة» أو «درس 1».',
            '',
            'أعد JSON فقط بدروس حقيقية مشتقّة من المخرجات أعلاه:',
            '{"lessons":["...","..."],"common_errors":["..."],"sharper_phrasing":"..."}',
        ]);

        $system = 'أنت معلّم خبير في التسويق والتقييم. تستخرج دروساً عامة محدّدة تُحسّن نظاماً آلياً. ممنوع العبارات النائبة أو نسخ التعليمات. أعد JSON صالحاً فقط.';

        $text = $gateway->generateText($prompt, $system);
        if (! $text) {
            continue;
        }

        $clean = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($text));
        $parsed = json_decode((string) $clean, true);
        if (! is_array($parsed) || empty($parsed['lessons'])) {
            continue;
        }

        // فلتر صارم: يرفض الدروس النائبة/المنسوخة من التعليمات (نموذج ضعيف يكرّر القالب).
        $promptNorm = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($prompt)));
        $isJunk = static function (string $s) use ($promptNorm): bool {
            $t = trim($s);
            if (mb_strlen($t) < 18) {
                return true;
            }
            $n = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($t)));
            if (str_contains($promptNorm, $n)) {
                return true; // منسوخ من التعليمات/المثال
            }

            return preg_match('/^(قاعدة|درس|نصيحة|مثال|خطأ)\s*\d*$/u', $t) === 1
                || str_contains($t, 'قابلة لإعادة الاستخدام')
                || str_contains($t, 'دروس عامة');
        };

        $clean_list = static function (array $list, callable $junk): array {
            $out = [];
            foreach ($list as $v) {
                if (is_string($v) && ! $junk($v)) {
                    $out[] = trim($v);
                }
            }

            return array_values(array_unique($out));
        };

        $lessons = $clean_list((array) ($parsed['lessons'] ?? []), $isJunk);
        if (count($lessons) < 2) {
            // مخرج ضعيف/منسوخ (غالباً نموذج احتياطي ضعيف) — لا نلوّث المعرفة.
            $this->warn("ai:teach — تجاهل دروس ضعيفة للأداة {$code}.");

            continue;
        }

        $phrasing = (string) ($parsed['sharper_phrasing'] ?? '');
        if ($isJunk($phrasing)) {
            $phrasing = '';
        }

        $knowledge->remember('teach.'.$code, [
            'tool' => $code,
            'lessons' => array_slice($lessons, 0, 5),
            'common_errors' => array_slice($clean_list((array) ($parsed['common_errors'] ?? []), $isJunk), 0, 4),
            'sharper_phrasing' => $phrasing,
            'sample_size' => count($samples),
        ]);
        $taught++;
    }

    $this->info(sprintf('ai:teach — علّم النظام المحلي دروساً عبر %d أداة.', $taught));
})->purpose('حلقة التعليم: LLM يقيّم مخرجات النظام المحلي ويستخرج دروساً يتعلّم منها (Teacher Loop).');

Schedule::command('ai:teach')
    ->dailyAt('04:45')
    ->withoutOverlapping()
    ->name('ai-teacher-loop');

Schedule::command('intelligence:monitoring-snapshots weekly')
    ->weeklyOn(1, '08:00')
    ->withoutOverlapping()
    ->name('intelligence-weekly-monitoring');

Schedule::command('intelligence:monitoring-snapshots monthly')
    ->monthlyOn(1, '09:00')
    ->withoutOverlapping()
    ->name('intelligence-monthly-monitoring');

/*
 | مراقب الأداء (performance_monitor) عبر cron: يبني سلسلة يومية لنشاط الأدوات
 | لكل مساحة ويرصد الشذوذ (>2σ) عبر AnomalyDetector المحلي، فيكتب إنذاراً في
 | KnowledgeStore. cron-only، بلا worker دائم، بلا مورد خارجي.
 */
Artisan::command('ai:monitor-performance {days=14}', function (AnomalyDetector $detector, KnowledgeStore $knowledge, int $days = 14): void {
    $alerts = 0;
    $checked = 0;

    Workspace::query()->orderBy('id')->chunkById(50, function ($workspaces) use ($detector, $knowledge, $days, &$alerts, &$checked): void {
        foreach ($workspaces as $workspace) {
            $series = ToolRun::query()
                ->where('workspace_id', $workspace->id)
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
                ->groupBy('d')
                ->orderBy('d')
                ->pluck('c')
                ->map(fn ($v): int => (int) $v)
                ->all();

            if (count($series) < 4) {
                continue;
            }
            $checked++;

            $result = $detector->detect($series);
            if ($result['status'] === 'anomaly') {
                $knowledge->remember('monitor.performance.ws'.$workspace->id, [
                    'workspace_id' => $workspace->id,
                    'window_days' => $days,
                    'series' => $series,
                    'mean' => $result['mean'],
                    'std' => $result['std'],
                    'anomalies' => $result['anomalies'],
                ]);
                $alerts++;
            }
        }
    });

    $this->info(sprintf('مراقبة الأداء: فُحصت %d مساحة، إنذارات شذوذ: %d.', $checked, $alerts));
})->purpose('رصد شذوذ نشاط الأدوات لكل مساحة عبر AnomalyDetector (performance_monitor).');

Schedule::command('ai:monitor-performance')
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->name('ai-performance-monitor');
