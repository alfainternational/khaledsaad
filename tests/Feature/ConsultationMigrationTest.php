<?php

namespace Tests\Feature;

use App\Models\AgencyReport;
use App\Models\Project;
use App\Models\User;
use App\Modules\Intake\ConsultationPrivacyService;
use App\Modules\Intake\ConsultationService;
use App\Services\Projects\ProjectService;
use Database\Seeders\ConsultationCatalogSeeder;
use Database\Seeders\ToolCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function legacy_entities_survive_consultation_creation_and_privacy_deletion(): void
    {
        $this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, ['name' => 'مشروع تاريخي', 'stage' => 'growth']);
        $report = AgencyReport::create([
            'project_id' => $project->id, 'created_by' => $user->id, 'version' => 1,
            'title' => 'تقرير تاريخي', 'status' => 'published', 'source_report_ids' => [],
            'visibility' => [], 'snapshot' => ['legacy' => true], 'generated_at' => now(),
        ]);
        $before = ['users' => User::count(), 'projects' => Project::count(), 'reports' => AgencyReport::count()];

        $session = app(ConsultationService::class)->start($project, $user);
        $report->forceFill(['consultation_session_id' => $session->id])->save();
        app(ConsultationPrivacyService::class)->delete($session);

        $this->assertSame($before['users'], User::count());
        $this->assertSame($before['projects'], Project::count());
        $this->assertSame($before['reports'], AgencyReport::count());
        $this->assertNull($report->refresh()->consultation_session_id);
        $this->assertTrue(Schema::hasColumns('recommendations', ['action_steps', 'owner_role', 'success_condition', 'stop_condition', 'confidence']));
    }
}
