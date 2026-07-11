<?php

namespace Tests\Unit\AI\Knowledge;

use App\Domain\AI\Knowledge\ProjectKnowledgeSnapshotBuilder;
use App\Domain\Project\Models\Project;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\WorkspaceData\Models\WorkspaceData;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectKnowledgeSnapshotBuilderTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::getDriverName() === 'sqlite' && config('database.connections.sqlite.database') === ':memory:') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    #[Test]
    public function it_builds_ordered_normalized_arabic_chunks_from_authorized_project_data_only(): void
    {
        $project = Project::query()->create([
            'public_id' => 'project-arabic',
            'workspace_id' => $this->workspaceId('Main'),
            'name' => "  مشروع عربي\r\nمتطور  ",
            'stage' => 2,
            'status' => 'active',
            'sector' => 'التقنية',
            'market_country' => 'السعودية',
            'primary_domain' => 'example.sa',
            'official_social_links_json' => ['linkedin' => 'https://linkedin.test/company', 'x' => ''],
            'verified_social_profiles_json' => ['instagram' => '@example'],
            'competitors_json' => ['منافس ب', 'منافس أ'],
            'analysis_goals_json' => ['زيادة المبيعات', 'تحسين الظهور'],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'key' => 'project.marketing_brief',
            'value_json' => ['business' => ['offer' => 'حل آمن'], 'api_key' => 'SECRET-123'],
        ]);
        WorkspaceData::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => null,
            'key' => 'private.credentials',
            'value_json' => ['password' => 'WORKSPACE-SECRET'],
        ]);
        ToolRun::query()->create([
            'public_id' => 'tool-run-safe-summary',
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'tool_code' => 'market-analysis',
            'mode' => 'quick',
            'summary_json' => ['summary' => 'فرصة نمو واضحة', 'api_key' => 'TOOL-SECRET'],
            'next_actions_json' => ['اختبار الرسالة'],
        ]);

        $snapshot = (new ProjectKnowledgeSnapshotBuilder)->build($project);

        $this->assertSame('مشروع عربي متطور', $snapshot['title']);
        $this->assertSame(['Project', 'Market', 'Channels', 'Competitors', 'Goals'], array_column($snapshot['chunks'], 'heading'));
        $this->assertSame(['project', 'market', 'channels', 'competitors', 'goals'], array_column(array_column($snapshot['chunks'], 'locator'), 'field'));
        $this->assertStringContainsString('التقنية', $snapshot['content']);
        $this->assertStringContainsString('حل آمن', $snapshot['content']);
        $this->assertStringContainsString('فرصة نمو واضحة', $snapshot['content']);
        $this->assertStringNotContainsString('SECRET-123', $snapshot['content']);
        $this->assertStringNotContainsString('TOOL-SECRET', $snapshot['content']);
        $this->assertStringNotContainsString('WORKSPACE-SECRET', $snapshot['content']);
    }

    private function workspaceId(string $suffix): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => "User {$suffix}", 'email' => strtolower($suffix).'@example.test',
            'password' => 'secret', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $accountId = DB::table('accounts')->insertGetId([
            'public_id' => "account-{$suffix}", 'owner_user_id' => $userId, 'name' => "Account {$suffix}",
            'billing_email' => strtolower($suffix).'@example.test', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('workspaces')->insertGetId([
            'public_id' => "workspace-{$suffix}", 'account_id' => $accountId,
            'name' => "Workspace {$suffix}", 'type' => 'team', 'status' => 'active',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
