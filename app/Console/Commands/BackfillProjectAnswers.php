<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\ToolRun;
use App\Services\Tools\ProjectAnswerMemory;
use Illuminate\Console\Command;

/**
 * استرجاع ما كتبه أصحاب المشاريع داخل الأدوات قبل تفعيل ذاكرة المشروع.
 *
 * قبل هذا التغيير كانت الإجابات تبقى حبيسة تشغيل الأداة الواحد، فيظهر
 * ملف المشروع فارغًا وتعيد كل أداة السؤال نفسه. هذا الأمر ينقل الإجابات
 * الموجودة أصلًا في قاعدة البيانات إلى ذاكرة المشروع وملفه.
 */
class BackfillProjectAnswers extends Command
{
    protected $signature = 'projects:backfill-answers {--project= : سلاق مشروع بعينه}';

    protected $description = 'نقل إجابات الأدوات السابقة إلى ملف المشروع وذاكرته';

    public function handle(ProjectAnswerMemory $memory): int
    {
        $projects = Project::query()
            ->when($this->option('project'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get();

        $totalAnswers = 0;

        foreach ($projects as $project) {
            // الأحدث أولًا: آخر إجابة يكتبها المستخدم هي الصحيحة.
            $runs = ToolRun::where('project_id', $project->id)
                ->with(['answers', 'toolVersion.tool', 'toolVersion.fields'])
                ->orderBy('id')
                ->get();

            $before = ProjectAnswer::where('project_id', $project->id)->count();

            foreach ($runs as $run) {
                $answers = $run->answers
                    ->mapWithKeys(fn ($answer) => [$answer->field_key => $answer->value_json['value'] ?? null])
                    ->filter(fn ($value) => $value !== null && $value !== '' && $value !== [])
                    ->all();

                if ($answers !== []) {
                    $memory->remember($run, $answers);
                }
            }

            $after = ProjectAnswer::where('project_id', $project->id)->count();
            $totalAnswers += $after;

            $this->line("- {$project->name}: {$after} إجابة محفوظة (كانت {$before})");
        }

        $this->info("اكتمل. {$projects->count()} مشروع، {$totalAnswers} إجابة في الذاكرة.");

        return self::SUCCESS;
    }
}
