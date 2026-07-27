<?php

namespace App\Services\Consultations\Catalog;

use App\Models\BlueprintModule;
use App\Models\ConsultationBlueprint;
use App\Models\ConsultationBlueprintVersion;
use App\Models\ModuleQuestion;
use App\Models\QuestionVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsultationCatalogManager
{
    public function __construct(private readonly ConsultationCatalogValidator $validator) {}

    public function createDraft(ConsultationBlueprint $blueprint): ConsultationBlueprintVersion
    {
        $existing = $blueprint->versions()->where('status', 'draft')->latest('version')->first();
        if ($existing) {
            return $existing;
        }
        $source = $blueprint->currentVersion()->with('modules.questions.questionVersion')->firstOrFail();

        return DB::transaction(function () use ($blueprint, $source): ConsultationBlueprintVersion {
            $draft = $blueprint->versions()->create([
                'version' => ((int) $blueprint->versions()->max('version')) + 1,
                'status' => 'draft',
                'settings' => $source->settings,
            ]);
            $clonedQuestions = [];
            foreach ($source->modules as $sourceModule) {
                $module = BlueprintModule::create([
                    'blueprint_version_id' => $draft->id,
                    'diagnostic_module_id' => $sourceModule->diagnostic_module_id,
                    'importance' => $sourceModule->importance,
                    'required' => $sourceModule->required,
                    'activation_rules' => $sourceModule->activation_rules,
                    'stop_rules' => $sourceModule->stop_rules,
                    'sort_order' => $sourceModule->sort_order,
                ]);
                foreach ($sourceModule->questions as $binding) {
                    $old = $binding->questionVersion;
                    $question = $clonedQuestions[$old->id] ??= QuestionVersion::create([
                        'question_definition_id' => $old->question_definition_id,
                        'version' => ((int) QuestionVersion::where('question_definition_id', $old->question_definition_id)->max('version')) + 1,
                        'user_text' => $old->user_text,
                        'help_text' => $old->help_text,
                        'why_text' => $old->why_text,
                        'answer_type' => $old->answer_type,
                        'options' => $old->options,
                        'validation' => $old->validation,
                        'required' => $old->required,
                        'allow_unknown' => $old->allow_unknown,
                        'allow_skip' => $old->allow_skip,
                    ]);
                    ModuleQuestion::create([
                        'blueprint_module_id' => $module->id,
                        'question_version_id' => $question->id,
                        'diagnostic_impact' => $binding->diagnostic_impact,
                        'discrimination' => $binding->discrimination,
                        'answer_burden' => $binding->answer_burden,
                        'critical' => $binding->critical,
                        'show_when' => $binding->show_when,
                        'follow_up_rules' => $binding->follow_up_rules,
                        'sort_order' => $binding->sort_order,
                    ]);
                }
            }

            return $draft;
        });
    }

    /** @param array<string,mixed> $data */
    public function updateQuestion(ConsultationBlueprintVersion $version, QuestionVersion $question, array $data): QuestionVersion
    {
        $this->assertDraft($version);
        $belongs = ModuleQuestion::where('question_version_id', $question->id)
            ->whereHas('blueprintModule', fn ($q) => $q->where('blueprint_version_id', $version->id))->exists();
        abort_unless($belongs, 404);
        $question->fill($data)->save();

        return $question->refresh();
    }

    public function publish(ConsultationBlueprintVersion $version): ConsultationBlueprintVersion
    {
        $this->assertDraft($version);
        $this->validator->validate($version);

        return DB::transaction(function () use ($version): ConsultationBlueprintVersion {
            QuestionVersion::whereHas('moduleBindings.blueprintModule', fn ($q) => $q->where('blueprint_version_id', $version->id))
                ->update(['locked_at' => now()]);
            $version->forceFill(['status' => 'published', 'published_at' => now(), 'locked_at' => now()])->save();
            $version->blueprint->forceFill(['status' => 'published', 'current_version_id' => $version->id])->save();

            return $version->refresh();
        });
    }

    private function assertDraft(ConsultationBlueprintVersion $version): void
    {
        if ($version->status !== 'draft' || $version->locked_at !== null) {
            throw ValidationException::withMessages(['version' => 'الإصدار المنشور مقفل. أنشئ مسودة جديدة للتعديل.']);
        }
    }
}
