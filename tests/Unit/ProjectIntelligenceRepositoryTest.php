<?php

namespace Tests\Unit;

use App\Domain\Account\Models\Account;
use App\Domain\Intelligence\Models\MonitorSnapshot;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Models\User;
use App\Support\Intelligence\ProjectIntelligenceRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectIntelligenceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_monitoring_trend_in_chronological_order_for_the_latest_points(): void
    {
        $user = User::factory()->create();

        $account = Account::query()->create([
            'owner_user_id' => $user->id,
            'name' => 'Trend Account',
            'billing_email' => $user->email,
            'status' => 'active',
        ]);

        $workspace = Workspace::query()->create([
            'account_id' => $account->id,
            'name' => 'Trend Workspace',
            'type' => 'agency',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Trend Project',
            'stage' => 3,
            'status' => 'active',
            'sector' => 'saas',
        ]);

        foreach ([
            ['date' => '2026-04-01 10:00:00', 'executive' => 40],
            ['date' => '2026-04-10 10:00:00', 'executive' => 55],
            ['date' => '2026-04-20 10:00:00', 'executive' => 73],
        ] as $item) {
            MonitorSnapshot::query()->create([
                'workspace_id' => $workspace->id,
                'project_id' => $project->id,
                'captured_at' => $item['date'],
                'executive_score' => $item['executive'],
                'website_score' => $item['executive'] + 1,
                'social_score' => $item['executive'] + 2,
                'seo_score' => $item['executive'] + 3,
                'trust_score' => $item['executive'] + 4,
                'conversion_score' => $item['executive'] + 5,
                'ads_readiness_score' => $item['executive'] + 6,
                'ai_visibility_score' => $item['executive'] + 7,
                'competition_score' => $item['executive'] + 8,
                'lead_readiness_score' => $item['executive'] + 9,
                'payload_json' => [],
            ]);
        }

        $trend = (new ProjectIntelligenceRepository)->trend($project, 2);

        $this->assertSame('2026-04-10', $trend[0]['captured_at']);
        $this->assertSame('2026-04-20', $trend[1]['captured_at']);
        $this->assertSame(55, $trend[0]['executive_score']);
        $this->assertSame(78, $trend[1]['conversion_score']);
    }
}
