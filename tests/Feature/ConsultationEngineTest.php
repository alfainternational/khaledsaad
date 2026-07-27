<?php

namespace Tests\Feature;

use App\Models\ConsultationSession;
use App\Models\User;
use App\Services\Consultations\ConsultationService;
use App\Services\Projects\ProjectService;
use App\Services\Tools\FullDiagnosisRunner;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
    }

    #[Test]
    public function it_starts_or_resumes_one_session_and_selects_questions_deterministically(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'متجر', 'stage' => 'growth']);
        $service = app(ConsultationService::class);

        $session = $service->start($project, $user, 'standard');

        $this->assertSame('START-01', $session->currentQuestion->definition->key);
        $this->assertCount(19, $session->moduleStates);
        $this->assertSame($session->id, $service->start($project, $user, 'standard')->id);

        $service->answer($session, $session->currentQuestion, ['value' => 'مشروع قائم']);
        $session->refresh()->load('currentQuestion.definition');

        $this->assertSame('START-02', $session->currentQuestion->definition->key);
        $this->assertSame(1, $session->questions_answered);
        $this->assertDatabaseHas('project_answers', ['project_id' => $project->id, 'field_key' => 'assessment_scope']);
    }

    #[Test]
    public function unknown_is_recorded_as_a_measurement_gap_not_a_zero_score(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'شركة', 'stage' => 'idea']);
        $service = app(ConsultationService::class);
        $session = $service->start($project, $user, 'quick');

        $service->answer($session, $session->currentQuestion, ['unknown' => true]);

        $this->assertDatabaseHas('consultation_answers', [
            'consultation_session_id' => $session->id,
            'is_unknown' => true,
            'confidence' => 'low',
        ]);
        $this->assertDatabaseHas('consultation_inferences', [
            'consultation_session_id' => $session->id,
            'type' => 'missing_information',
        ]);
    }

    #[Test]
    public function review_confirmation_queues_one_full_diagnosis_for_the_session(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'تقرير موحد', 'stage' => 'growth']);
        $session = app(ConsultationService::class)->start($project, $user, 'quick');
        $session->forceFill(['status' => ConsultationSession::STATUS_REVIEW, 'current_question_version_id' => null])->save();

        $runner = $this->mock(FullDiagnosisRunner::class);
        $runner->shouldReceive('run')->once()->withArgs(fn ($actualProject, $actualUser, $mode, $sessionId) => $actualProject->is($project) && $actualUser->is($user) && $mode === FullDiagnosisRunner::MODE_AUTO && $sessionId === $session->id)
            ->andReturn(['started_count' => 11, 'skipped_count' => 0]);

        app(ConsultationService::class)->confirm($session->refresh(), $user);

        $this->assertSame(ConsultationSession::STATUS_QUEUED, $session->refresh()->status);
        $this->assertNotNull($session->confirmed_at);
    }

    #[Test]
    public function gateway_answers_refresh_scope_and_selected_depth(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'نطاق متكيف', 'stage' => 'growth']);
        $service = app(ConsultationService::class);
        $session = $service->start($project, $user);

        foreach (['مشروع قائم', 'خدمة', 'شركات'] as $value) {
            $service->answer($session->refresh()->load('currentQuestion.definition'), $session->currentQuestion, ['value' => $value]);
        }
        $this->assertSame('not_applicable', $session->refresh()->moduleStates()->whereHas('module', fn ($q) => $q->where('key', 'customer-b2c'))->value('state'));
        $this->assertSame('supporting', $session->moduleStates()->whereHas('module', fn ($q) => $q->where('key', 'customer-b2b'))->value('state'));

        foreach (['نمو', 'ضعف المبيعات', 'زيادة المبيعات', '3 أشهر', 'تقديرات فقط', 'متعمق'] as $value) {
            $service->answer($session->refresh()->load('currentQuestion.definition'), $session->currentQuestion, ['value' => $value]);
        }
        $this->assertSame('deep', $session->refresh()->depth);
    }

    #[Test]
    public function api_can_revise_a_previous_answer_without_changing_question_order(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'تصحيح', 'stage' => 'growth']);
        $service = app(ConsultationService::class);
        $session = $service->start($project, $user);
        $service->answer($session, $session->currentQuestion, ['value' => 'مشروع قائم']);
        Sanctum::actingAs($user);

        $this->putJson(route('api.v1.consultations.answer', [$session, 'START-01']), ['value' => 'حملة'])
            ->assertOk()
            ->assertJsonPath('data.question.key', 'START-02');

        $answer = $session->answers()->whereHas('questionVersion.definition', fn ($q) => $q->where('key', 'START-01'))->firstOrFail();
        $this->assertSame('حملة', data_get($answer->value_json, 'value'));
    }
}
