<?php

namespace Tests\Feature;

use App\Models\ProjectKnowledgeSource;
use App\Models\Tool;
use App\Models\User;
use App\Modules\Intake\ConsultationService;
use App\Services\Projects\ProjectService;
use App\Services\Tools\ToolRunService;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectKnowledgeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function profile_tool_and_consultation_writes_share_one_current_value_and_keep_every_source(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع موحد',
            'value_proposition' => 'قيمة من ملف المشروع',
        ]);

        $run = app(ToolRunService::class)->start(
            $project,
            Tool::where('key', 'marketing-score')->firstOrFail(),
            $user,
        );
        app(ToolRunService::class)->saveStep($run, 2, [
            'primary_goal' => 'sales',
            'value_proposition' => 'قيمة أدق من أداة التشخيص',
            'audience_clarity' => 'rough',
        ]);

        $session = app(ConsultationService::class)->start($project->fresh(), $user);
        $question = $session->currentQuestion->load('definition');
        app(ConsultationService::class)->answer($session, $question, ['value' => 'مشروع قائم']);

        $this->assertSame(
            'قيمة أدق من أداة التشخيص',
            $project->answers()->where('field_key', 'value_proposition')->firstOrFail()->value(),
        );
        $this->assertDatabaseHas('project_knowledge_sources', [
            'project_id' => $project->id,
            'field_key' => 'value_proposition',
            'source_type' => 'profile',
            'event_type' => 'asserted',
        ]);
        $this->assertDatabaseHas('project_knowledge_sources', [
            'project_id' => $project->id,
            'field_key' => 'value_proposition',
            'source_type' => 'tool',
            'source_id' => $run->id,
            'event_type' => 'asserted',
        ]);
        $this->assertDatabaseHas('project_knowledge_sources', [
            'project_id' => $project->id,
            'field_key' => $question->definition->internal_variable,
            'source_type' => 'consultation',
            'source_id' => $session->id,
            'event_type' => 'asserted',
        ]);

        $history = ProjectKnowledgeSource::where('project_id', $project->id)
            ->where('field_key', 'value_proposition')
            ->oldest('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame('قيمة من ملف المشروع', $history[0]->value());
        $this->assertSame('قيمة أدق من أداة التشخيص', $history[1]->value());
    }

    #[Test]
    public function retracting_a_consultation_answer_removes_only_the_current_projection_not_its_history(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'سجل لا يمحى']);
        $service = app(ConsultationService::class);
        $session = $service->start($project, $user);
        $question = $session->currentQuestion->load('definition');

        $service->answer($session, $question, ['value' => 'مشروع قائم']);
        $service->revise($session->refresh(), $question, ['unknown' => true]);

        $this->assertDatabaseMissing('project_answers', [
            'project_id' => $project->id,
            'field_key' => $question->definition->internal_variable,
        ]);
        $this->assertSame(
            ['asserted', 'retracted'],
            ProjectKnowledgeSource::where('project_id', $project->id)
                ->where('field_key', $question->definition->internal_variable)
                ->oldest('id')->pluck('event_type')->all(),
        );
    }
}
