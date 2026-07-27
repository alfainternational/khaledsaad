<?php

namespace App\Services\Tools;

use App\Models\Project;
use App\Models\ProjectAnswer;
use App\Models\ProjectProfile;
use App\Models\ToolField;
use App\Models\ToolRun;
use App\Models\ToolRunAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ذاكرة المشروع: تُكتب مرة، وتُقرأ في كل مكان.
 *
 * المشكلة التي تحلها: صاحب المشروع كان يكتب وصف مشروعه وسبب الشراء منه
 * داخل أداة، ثم يجد ملف المشروع فارغًا وأداة أخرى تسأله السؤال نفسه.
 *
 * الحل في اتجاهين:
 * 1) ما يُكتب داخل أي أداة يُحفظ في ملف المشروع وفي ذاكرته.
 * 2) أي أداة تُفتح لاحقًا تبدأ بالإجابات المعروفة بدل صفحة فارغة.
 */
class ProjectAnswerMemory
{
    /**
     * حفظ إجابات خطوة داخل ذاكرة المشروع وملفه.
     *
     * @param  array<string, mixed>  $answers
     */
    public function remember(ToolRun $run, array $answers): void
    {
        if ($answers === []) {
            return;
        }

        $run->loadMissing(['project.profile', 'toolVersion.tool', 'toolVersion.fields']);
        $project = $run->project;
        $fields = $run->toolVersion->fields->keyBy('key');
        $toolKey = $run->toolVersion->tool?->key;

        DB::transaction(function () use ($project, $answers, $fields, $run, $toolKey): void {
            $profileUpdates = [];

            foreach ($answers as $key => $value) {
                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                ProjectAnswer::updateOrCreate(
                    ['project_id' => $project->id, 'field_key' => $key],
                    [
                        'value_json' => ['value' => $value],
                        'source_tool_key' => $toolKey,
                        'source_run_id' => $run->id,
                    ],
                );

                // الحقول التي لها مكان في ملف المشروع تُكتب فيه أيضًا،
                // فيظهر الملف ممتلئًا بما كتبه المستخدم داخل الأداة.
                $profileKey = $fields->get($key)?->profile_key;

                if ($profileKey !== null) {
                    $profileUpdates[$profileKey] = $value;
                }
            }

            if ($profileUpdates !== []) {
                $this->writeProfile($project, $profileUpdates);
            }
        });
    }

    /**
     * تعبئة تشغيل جديد بما يعرفه المشروع مسبقًا.
     *
     * @return array<int, string> مفاتيح الحقول التي مُلئت من الذاكرة
     */
    public function prefill(ToolRun $run): array
    {
        $run->loadMissing(['project', 'toolVersion.fields']);

        $known = ProjectAnswer::where('project_id', $run->project_id)
            ->pluck('value_json', 'field_key');

        $filled = [];

        foreach ($run->toolVersion->fields as $field) {
            $stored = $known->get($field->key);
            $value = is_array($stored) ? ($stored['value'] ?? null) : null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            ToolRunAnswer::updateOrCreate(
                ['tool_run_id' => $run->id, 'field_key' => $field->key],
                ['value_json' => ['value' => $value], 'source' => ToolRunAnswer::SOURCE_PROFILE],
            );

            $filled[] = $field->key;
        }

        return $filled;
    }

    /**
     * مزامنة ملف المشروع إلى الذاكرة — يستدعى عند تعديل الملف يدويًا.
     *
     * @param  array<string, mixed>  $data
     */
    public function rememberProfile(Project $project, array $data): void
    {
        foreach ($data as $key => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            ProjectAnswer::updateOrCreate(
                ['project_id' => $project->id, 'field_key' => $key],
                ['value_json' => ['value' => $value], 'source_tool_key' => null, 'source_run_id' => null],
            );
        }
    }

    /**
     * ما يعرفه المشروع بالفعل، معروضًا بلغة المستخدم.
     *
     * @param  Collection<int, ToolField>  $fields
     * @return array<int, array{key: string, label: string, value: mixed}>
     */
    /**
     * كل ما يعرفه المشروع بمفاتيحه وقيمه المجردة — للاستعراض والحساب لا للعرض.
     *
     * @return array<string, mixed>
     */
    public function knownValues(Project $project): array
    {
        return ProjectAnswer::where('project_id', $project->id)
            ->get()
            ->mapWithKeys(fn (ProjectAnswer $answer) => [
                $answer->field_key => $answer->value_json['value'] ?? $answer->value_json,
            ])
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, value: mixed}>
     */
    public function knownFor(Project $project, $fields): array
    {
        $known = ProjectAnswer::where('project_id', $project->id)->pluck('value_json', 'field_key');

        return $fields
            ->filter(fn (ToolField $field) => $known->has($field->key))
            ->map(fn (ToolField $field) => [
                'key' => $field->key,
                'label' => $field->label,
                'value' => $known->get($field->key)['value'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $updates
     */
    private function writeProfile(Project $project, array $updates): void
    {
        $profile = $project->profile ?? new ProjectProfile(['project_id' => $project->id]);
        $fillable = $profile->getFillable();

        foreach ($updates as $key => $value) {
            if (in_array($key, $fillable, true)) {
                $profile->{$key} = $value;
            }
        }

        $profile->project_id = $project->id;
        $profile->save();
        $project->setRelation('profile', $profile);
    }
}
