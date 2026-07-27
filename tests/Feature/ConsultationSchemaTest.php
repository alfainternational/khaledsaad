<?php

namespace Tests\Feature;

use App\Models\ConsultationBlueprint;
use App\Models\ConsultationBlueprintVersion;
use App\Models\ConsultationSession;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsultationSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function consultation_schema_is_additive_and_links_projects_to_versioned_sessions(): void
    {
        $user = User::factory()->create();
        $project = app(ProjectService::class)->create($user, [
            'name' => 'مشروع الاستشارة',
            'stage' => 'growth',
        ]);
        $blueprint = ConsultationBlueprint::create([
            'key' => 'smart-marketing-consultation',
            'name' => 'الاستشارة التسويقية الذكية',
            'status' => 'published',
        ]);
        $version = ConsultationBlueprintVersion::create([
            'consultation_blueprint_id' => $blueprint->id,
            'version' => 1,
            'status' => 'published',
            'settings' => ['depth_limits' => ['standard' => 35]],
            'published_at' => now(),
        ]);
        $blueprint->forceFill(['current_version_id' => $version->id])->save();

        $session = ConsultationSession::create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'blueprint_version_id' => $version->id,
            'status' => ConsultationSession::STATUS_ACTIVE,
            'depth' => 'standard',
        ]);

        $this->assertSame($project->id, $session->project->id);
        $this->assertSame($version->id, $session->blueprintVersion->id);
        $this->assertTrue($project->consultationSessions->contains($session));
        $this->assertNotEmpty($session->uuid);
    }
}
