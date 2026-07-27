<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Tools\FullDiagnosisRunner;
use Illuminate\Console\Command;

/**
 * تشغيل الأدوات كلها لمشروع بأمر واحد من الطرفية.
 *
 * نظير الزر في اللوحة تمامًا — نفس الخدمة ونفس القواعد، حتى لا يصبح
 * للطرفية سلوك يخالف ما يراه المستخدم.
 */
class RunFullDiagnosis extends Command
{
    protected $signature = 'diagnosis:full
        {project : معرّف المشروع أو مُعرّفه النصي}
        {--mode=auto : auto للتحليل الآلي أو manual للمراجعة البشرية}
        {--preview : استعراض التغطية دون تشغيل}';

    protected $description = 'تشخيص شامل: تشغيل كل الأدوات ثم بناء المستند الموحّد';

    public function handle(FullDiagnosisRunner $runner): int
    {
        $project = Project::where('slug', $this->argument('project'))
            ->orWhere('id', $this->argument('project'))
            ->with('profile')
            ->first();

        if ($project === null) {
            $this->error('لم يُعثر على المشروع.');

            return self::FAILURE;
        }

        $preview = $runner->preview($project);

        $this->line("المشروع: {$project->name}");
        $this->line("الأدوات: {$preview['tool_count']} · تغطية الأسئلة الإلزامية: {$preview['coverage_percent']}٪");

        $this->table(
            ['الأداة', 'التغطية', 'أبرز ما ينقص'],
            collect($preview['tools'])->map(fn (array $tool) => [
                $tool['title'],
                $tool['percent'].'٪',
                implode('، ', $tool['missing']) ?: '—',
            ])->all(),
        );

        if ($preview['needs_warning']) {
            $this->warn($preview['warning']);
        }

        if ($this->option('preview')) {
            return self::SUCCESS;
        }

        $owner = $project->workspace?->owner;

        if ($owner === null) {
            $this->error('لا مالك لمساحة عمل هذا المشروع.');

            return self::FAILURE;
        }

        $result = $runner->run($project, $owner, (string) $this->option('mode'));

        $this->info($result['message']);

        foreach ($result['skipped'] as $skipped) {
            $this->warn("تعذّر «{$skipped['title']}»: {$skipped['reason']}");
        }

        return $result['started_count'] > 0 ? self::SUCCESS : self::FAILURE;
    }
}
