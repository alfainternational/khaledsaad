<?php

namespace Tests\Feature;

use App\Models\Tool;
use App\Models\User;
use App\Modules\Brain\Models\BrainEvent;
use App\Modules\Brain\Models\BrainFact;
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
        // كل مصدر يترك أثره في الدماغ باسم وحدته، لا في جدول معرفة ثانٍ.
        $this->assertDatabaseHas('brain_facts', [
            'project_id' => $project->id,
            'key' => 'value_proposition',
            'source_module' => 'Profile',
        ]);
        $this->assertDatabaseHas('brain_facts', [
            'project_id' => $project->id,
            'key' => 'value_proposition',
            'source_module' => 'PlatformBridge',
            'source_reference' => "tool:marketing-score:{$run->id}",
        ]);
        $this->assertDatabaseHas('brain_facts', [
            'project_id' => $project->id,
            'key' => $question->definition->internal_variable,
            'source_module' => 'Intake',
        ]);

        $history = BrainFact::where('project_id', $project->id)
            ->where('key', 'value_proposition')
            ->oldest('id')->get();
        $this->assertCount(2, $history);
        $this->assertSame('قيمة من ملف المشروع', $history[0]->value_json['value']);
        $this->assertSame('قيمة أدق من أداة التشخيص', $history[1]->value_json['value']);

        /*
         * مصدران مختلفان قالا شيئين مختلفين، فبقيت الروايتان ونشأ حدث تعارض.
         * لا استبدال هنا: الاستبدال لمن يصحّح نفسه، والتعارض لمن يخالف غيره —
         * وأن يصف صاحب النشاط قيمته بشكل وتقول أداة التشخيص بشكل آخر معلومة
         * تستحق المراجعة لا حسمًا صامتًا (§٩).
         */
        $this->assertNull($history[0]->superseded_by);
        $this->assertNull($history[1]->superseded_by);

        $conflicts = BrainEvent::where('project_id', $project->id)
            ->where('type', BrainEvent::TYPE_FACT_CONFLICT)
            ->whereNull('outcome')->get();
        $this->assertTrue(
            $conflicts->contains(fn (BrainEvent $event) => ($event->body['key'] ?? null) === 'value_proposition'),
            'التعارض بين الملف والأداة يجب أن يُعلَّم للمراجعة.',
        );

        // ومع ذلك يبقى للشاشة قيمة واحدة: الإسقاط يعرض الأحدث.
        $this->assertSame(
            'قيمة أدق من أداة التشخيص',
            $project->answers()->where('field_key', 'value_proposition')->firstOrFail()->value(),
        );
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
        $key = $question->definition->internal_variable;

        // الحقيقة تبقى بقيمتها وتخرج من السريان وحده.
        $fact = BrainFact::where('project_id', $project->id)->where('key', $key)->firstOrFail();
        $this->assertNotNull($fact->retracted_at);
        $this->assertSame('Intake', $fact->retracted_by_module);
        $this->assertSame('مشروع قائم', $fact->value_json['value']);

        $this->assertSame(
            0,
            BrainFact::where('project_id', $project->id)->where('key', $key)->active()->count(),
            'المسحوبة لا تُعدّ سارية.',
        );

        $this->assertDatabaseHas('brain_events', [
            'project_id' => $project->id,
            'type' => BrainEvent::TYPE_FACT_RETRACTED,
        ]);
    }
}
