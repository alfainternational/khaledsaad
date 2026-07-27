<?php

namespace App\Services\Consultations\Catalog;

use App\Models\ConsultationBlueprintVersion;
use App\Services\Consultations\AnswerTypeRegistry;
use Illuminate\Validation\ValidationException;

class ConsultationCatalogValidator
{
    public function validate(ConsultationBlueprintVersion $version): void
    {
        $version->load('modules.module', 'modules.questions.questionVersion.definition');
        $errors = [];
        if ($version->modules->count() !== count(config('consultation.modules'))) {
            $errors['modules'] = 'يجب أن يحتوي الإصدار جميع وحدات التشخيص المعتمدة.';
        }
        foreach ($version->modules as $module) {
            if ($module->module === null) {
                $errors["modules.{$module->id}"] = 'كل وحدة تحتاج تعريفًا صالحًا.';
            }
            foreach ($module->questions as $binding) {
                $question = $binding->questionVersion;
                if ($question?->definition === null || blank($question->definition->internal_variable)
                    || blank($question->user_text) || ! in_array($question->answer_type, AnswerTypeRegistry::all(), true)
                    || $binding->diagnostic_impact < 1 || $binding->diagnostic_impact > 5) {
                    $errors["questions.{$binding->id}"] = 'السؤال يفتقد نصًا أو متغيرًا أو نوعًا أو أثرًا تشخيصيًا صالحًا.';
                }
                if (in_array($question?->answer_type, ['select', 'radio', 'multiselect', 'ranking'], true) && empty($question?->options)) {
                    $errors["questions.{$binding->id}.options"] = 'سؤال الاختيار يحتاج خيارات.';
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
