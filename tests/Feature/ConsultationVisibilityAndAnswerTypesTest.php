<?php

namespace Tests\Feature;

use App\Models\QuestionDefinition;
use App\Models\QuestionVersion;
use App\Models\User;
use App\Services\Consultations\AnswerTypeRegistry;
use App\Services\Consultations\ConsultationPresenter;
use App\Services\Consultations\ConsultationService;
use App\Services\Consultations\Engine\AnswerValidator;
use App\Services\Projects\ProjectService;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationVisibilityAndAnswerTypesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function smart_consultation_has_a_global_customer_entry_and_project_actions(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        app(ProjectService::class)->create($user, ['name' => 'مشروعي', 'stage' => 'growth']);

        $this->actingAs($user)->get(route('app.dashboard'))
            ->assertOk()->assertSee('ابدأ التشخيص الذكي الشامل');
        $this->actingAs($user)->get(route('app.consultations.index'))
            ->assertOk()->assertSee('مشروعي')->assertSee('ابدأ الاستشارة');
    }

    #[Test]
    public function questions_that_can_have_simultaneous_answers_are_not_forced_to_one_choice(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);

        foreach (['START-01', 'START-02', 'START-05', 'START-10', 'START-11'] as $key) {
            $this->assertSame(
                'multiselect',
                QuestionDefinition::where('key', $key)->firstOrFail()->versions()->firstOrFail()->answer_type,
                "{$key} should accept more than one answer",
            );
        }
    }

    #[Test]
    public function the_web_review_renders_answer_fields_with_their_complete_validation_contract(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع المراجعة', 'stage' => 'growth']);
        $service = app(ConsultationService::class);
        $session = $service->start($project, $user);
        $question = $session->currentQuestion()->with('definition')->firstOrFail();
        $service->answer($session, $question, ['value' => [$question->options[0]['value']]]);
        $session->forceFill(['status' => 'review', 'current_question_version_id' => null])->save();
        $review = app(ConsultationPresenter::class)->show($session->refresh())['review'];
        $reviewItem = collect([$review['facts'], $review['estimates'], $review['unknowns']])->flatten(1)->first();

        $this->assertNotNull($reviewItem);
        $this->assertArrayHasKey('required', $reviewItem);

        $this->actingAs($user)->get(route('app.consultations.show', $session))
            ->assertOk()
            ->assertSee('صحّح الإجابة');
    }

    #[Test]
    public function the_consultation_validator_supports_every_declared_answer_format(): void
    {
        $this->assertSame([
            'text', 'textarea', 'select', 'radio', 'multiselect', 'boolean', 'confirmation',
            'number', 'range', 'scale', 'url', 'email', 'date', 'ranking', 'repeater',
        ], AnswerTypeRegistry::all());

        $validator = app(AnswerValidator::class);
        $validator->validate($this->question('range', [], ['min' => 0, 'max' => 100]), ['value' => ['min' => 10, 'max' => 40]]);
        $validator->validate($this->question('scale', [], ['min' => 1, 'max' => 10]), ['value' => 7]);
        $validator->validate($this->question('ranking', ['a', 'b', 'c']), ['value' => ['a' => 1, 'b' => 2, 'c' => 3]]);
        $validator->validate($this->question('repeater', [], ['max_items' => 5]), ['value' => ['أول', 'ثانٍ']]);

        $this->addToAssertionCount(4);
    }

    private function question(string $type, array $options, array $validation = []): QuestionVersion
    {
        $definition = QuestionDefinition::create([
            'key' => 'TEST-'.strtoupper($type), 'internal_variable' => 'test_'.$type,
            'sensitivity' => 'normal', 'inferable' => false,
        ]);

        return QuestionVersion::create([
            'question_definition_id' => $definition->id, 'version' => 1,
            'user_text' => 'سؤال', 'answer_type' => $type,
            'options' => array_map(fn ($value) => ['value' => $value, 'label' => $value], $options),
            'validation' => $validation, 'required' => true,
            'allow_unknown' => false, 'allow_skip' => false,
        ]);
    }
}
