<?php

namespace Tests\Feature;

use App\Models\ConsultationEvent;
use App\Models\ConsultationSession;
use App\Models\ProjectAnswer;
use App\Models\User;
use App\Services\Consultations\ConsultationEventRecorder;
use App\Services\Consultations\Engine\ConflictDetector;
use App\Services\Projects\ProjectService;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
    }

    #[Test]
    public function review_status_export_and_delete_are_owned_and_complete(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'استشارة خاصة', 'stage' => 'growth']);
        Sanctum::actingAs($user);

        $created = $this->postJson(route('api.v1.consultations.store', $project));
        $uuid = $created->json('data.uuid');
        $session = ConsultationSession::where('uuid', $uuid)->firstOrFail();
        $session->forceFill(['status' => ConsultationSession::STATUS_REVIEW, 'current_question_version_id' => null])->save();

        $this->postJson(route('api.v1.consultations.review', $uuid))
            ->assertOk()
            ->assertJsonPath('data.status', ConsultationSession::STATUS_REVIEW)
            ->assertJsonStructure(['data' => ['review' => ['facts', 'estimates', 'unknowns', 'assumptions', 'conflicts']]]);

        $this->getJson(route('api.v1.consultations.status', $uuid))
            ->assertOk()
            ->assertJsonPath('data.status', ConsultationSession::STATUS_REVIEW);

        $this->getJson(route('api.v1.consultations.export', $uuid))
            ->assertOk()
            ->assertJsonPath('data.session.uuid', $uuid);

        $this->deleteJson(route('api.v1.consultations.destroy', $uuid))->assertNoContent();
        $this->assertDatabaseMissing('consultation_sessions', ['uuid' => $uuid]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    #[Test]
    public function conflicts_are_detected_and_must_be_resolved_before_confirmation(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'تعارض', 'stage' => 'idea']);
        $session = $this->createConsultation($user, $project);

        ProjectAnswer::create(['project_id' => $project->id, 'field_key' => 'actual_stage', 'value_json' => ['value' => 'فكرة'], 'source_tool_key' => 'consultation']);
        ProjectAnswer::create(['project_id' => $project->id, 'field_key' => 'monthly_sales', 'value_json' => ['value' => 25], 'source_tool_key' => 'consultation']);

        app(ConflictDetector::class)->refresh($session);
        $session->forceFill(['status' => ConsultationSession::STATUS_REVIEW, 'current_question_version_id' => null])->save();

        $this->assertDatabaseHas('consultation_conflicts', ['consultation_session_id' => $session->id, 'key' => 'stage_vs_sales', 'status' => 'open']);
        Sanctum::actingAs($user);
        $this->postJson(route('api.v1.consultations.confirm', $session))->assertStatus(409);

        $conflict = $session->conflicts()->firstOrFail();
        $this->postJson(route('api.v1.consultations.conflicts.resolve', [$session, $conflict]), ['resolution' => 'المبيعات تجريبية والمرحلة الصحيحة قبل الإطلاق'])
            ->assertOk()
            ->assertJsonPath('data.conflicts.0', null);
    }

    #[Test]
    public function analytics_events_reject_sensitive_metadata(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'أحداث آمنة', 'stage' => 'growth']);
        $session = $this->createConsultation($user, $project);

        app(ConsultationEventRecorder::class)->record($session, 'answer_saved', [
            'question_key' => 'START-01',
            'answer' => 'سر تجاري',
            'email' => 'owner@example.com',
            'source' => 'web',
        ]);

        $event = ConsultationEvent::where('name', 'answer_saved')->firstOrFail();
        $this->assertSame(['question_key' => 'START-01', 'source' => 'web'], $event->metadata);
        $this->assertStringNotContainsString('example.com', json_encode($event->metadata));
    }

    private function createConsultation(User $user, $project): ConsultationSession
    {
        Sanctum::actingAs($user);
        $uuid = $this->postJson(route('api.v1.consultations.store', $project))->json('data.uuid');

        return ConsultationSession::where('uuid', $uuid)->firstOrFail();
    }
}
