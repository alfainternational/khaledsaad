<?php

namespace Tests\Feature;

use App\Support\Marketing\BriefQuestions;
use Tests\TestCase;

class UserFacingQuestionCopyTest extends TestCase
{
    public function test_every_canonical_question_uses_the_approved_copy_model(): void
    {
        foreach (glob(database_path('data/tools/*.php')) as $path) {
            $tool = require $path;

            foreach ($tool['fields'] as $field) {
                $reference = $tool['key'].'.'.$field['key'];

                $this->assertStringEndsWith('؟', $field['label'], $reference.' must be a direct question.');
                $this->assertNotEmpty($field['why'] ?? null, $reference.' must explain why the question is asked.');
            }
        }

        foreach (config('consultation.gateway_questions') as $question) {
            $this->assertStringEndsWith('؟', $question['text'], $question['key'].' must be a direct question.');
            $this->assertNotEmpty($question['help'] ?? null, $question['key'].' must explain how to answer.');
            $this->assertNotEmpty($question['why'] ?? null, $question['key'].' must explain why the question is asked.');
        }

        foreach (BriefQuestions::fields() as $field) {
            $this->assertStringEndsWith('؟', $field['label'], 'agency-brief.'.$field['key'].' must be a direct question.');
            $this->assertNotEmpty($field['why'] ?? null, 'agency-brief.'.$field['key'].' must explain why the question is asked.');
        }
    }

    public function test_question_renderers_use_the_approved_reason_heading(): void
    {
        $runField = file_get_contents(resource_path('views/app/runs/partials/field.blade.php'));
        $consultation = file_get_contents(resource_path('views/app/consultations/show.blade.php'));

        $this->assertStringContainsString('<summary>لماذا نسأل؟</summary>', $runField);
        $this->assertStringNotContainsString('لماذا نسأل عن هذه؟', $runField);
        $this->assertStringContainsString('<strong>لماذا نسأل؟</strong>', $consultation);
        $this->assertLessThan(strpos($consultation, '<form method="POST"'), strpos($consultation, "['help']"));
        $this->assertGreaterThan(strpos($consultation, '</form>'), strpos($consultation, '<strong>لماذا نسأل؟</strong>'));
    }

    public function test_question_facing_sources_avoid_rejected_dialect_and_legacy_phrases(): void
    {
        $copy = collect($this->questionFacingSourcePaths())
            ->map(fn (string $path): string => file_get_contents($path))
            ->implode("\n");

        foreach ([' جاوب ', ' شغلك ', ' عندك ', 'أي واحدة من هذه تحصل معك', 'لماذا نسأل عن هذه؟'] as $rejected) {
            $this->assertStringNotContainsString($rejected, $copy, 'Rejected user-facing phrase: '.$rejected);
        }
    }

    public function test_project_profile_questions_follow_the_same_question_help_and_reason_model(): void
    {
        $create = file_get_contents(resource_path('views/app/projects/create.blade.php'));
        $edit = file_get_contents(resource_path('views/app/projects/edit.blade.php'));

        foreach ([$create, $edit] as $view) {
            $this->assertStringContainsString('ما اسم مشروعك؟', $view);
            $this->assertStringContainsString('في أي مجال يعمل مشروعك؟', $view);
            $this->assertStringContainsString('<summary>لماذا نسأل؟</summary>', $view);
        }
    }

    public function test_agency_brief_questions_show_guidance_before_the_control_and_reason_after_it(): void
    {
        $brief = file_get_contents(resource_path('views/app/agency-reports/index.blade.php'));

        $this->assertStringContainsString("@php(\$guidance = match (\$field['type'])", $brief);
        $this->assertStringContainsString('<summary>لماذا نسأل؟</summary>', $brief);
        $this->assertGreaterThan(strpos($brief, '@endif'), strrpos($brief, '<details class="field__why">'));
    }

    /**
     * @return array<int, string>
     */
    private function questionFacingSourcePaths(): array
    {
        return [
            ...glob(database_path('data/tools/*.php')),
            config_path('consultation.php'),
            app_path('Support/Marketing/BriefQuestions.php'),
            resource_path('views/app/runs/partials/field.blade.php'),
            resource_path('views/app/consultations/show.blade.php'),
            resource_path('views/app/projects/create.blade.php'),
            resource_path('views/app/projects/edit.blade.php'),
            resource_path('views/app/agency-reports/index.blade.php'),
            base_path('mobile/lib/features/tools/run_wizard_screen.dart'),
            base_path('mobile/lib/features/consultations/consultation_screen.dart'),
        ];
    }
}
