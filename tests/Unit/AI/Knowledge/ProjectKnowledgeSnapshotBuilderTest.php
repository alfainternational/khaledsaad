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
            'official_social_links_json' => [
                'linkedin' => 'https://linkedin.test/company',
                'signed' => 'https://example.test/page?ok=1&access_token=URL-SECRET&X-Amz-Signature=SIGNATURE-SECRET',
                'x' => '',
            ],
            'verified_social_profiles_json' => ['instagram' => '@example'],
            'competitors_json' => ['منافس ب', 'منافس أ'],
            'analysis_goals_json' => ['زيادة المبيعات', 'تحسين الظهور'],
        ]);

        WorkspaceData::query()->create([
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'key' => 'project.marketing_brief',
            'value_json' => [
                'business' => [
                    'offer' => [
                        'safe' => 'حل آمن',
                        'authorization' => 'AUTHORIZATION-SECRET',
                        "multi.line\n~key" => [
                            'items' => [
                                ['name' => 'عنصر', 'private_key' => 'PRIVATE-KEY-SECRET'],
                                '1',
                                1,
                                null,
                                1.5,
                            ],
                        ],
                    ],
                ],
                'api_key' => 'SECRET-123',
            ],
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
        ToolRun::query()->create([
            'public_id' => 'tool-run-latest-summary',
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'tool_code' => 'market-analysis',
            'mode' => 'quick',
            'summary_json' => [
                'summary' => 'أحدث فرصة نمو',
                'nested' => [
                    'cookie' => 'COOKIE-SECRET',
                    'access_key_id' => 'ACCESS-KEY-SECRET',
                    'tokens' => ['TOKEN-SECRET'],
                    'safe_url' => 'https://example.test/callback?code=SAFE&signature=QUERY-SIGNATURE-SECRET',
                ],
            ],
            'next_actions_json' => ['البدء الآن'],
        ]);
        ToolRun::query()->create([
            'public_id' => 'tool-run-content-old',
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'tool_code' => 'content-plan',
            'mode' => 'quick',
            'summary_json' => ['summary' => 'ملخص قديم لا يجب تحميله'],
        ]);
        ToolRun::query()->create([
            'public_id' => 'tool-run-content-latest',
            'workspace_id' => $project->workspace_id,
            'project_id' => $project->id,
            'tool_code' => 'content-plan',
            'mode' => 'quick',
            'summary_json' => null,
            'next_actions_json' => ['أحدث إجراء فقط'],
        ]);

        $snapshot = (new ProjectKnowledgeSnapshotBuilder)->build($project);

        $this->assertSame('مشروع عربي متطور', $snapshot['title']);
        $this->assertSame(['Project', 'Market', 'Channels', 'Competitors', 'Goals'], array_column($snapshot['chunks'], 'heading'));
        $this->assertSame(['project', 'market', 'channels', 'competitors', 'goals'], array_column(array_column($snapshot['chunks'], 'locator'), 'field'));
        $this->assertStringContainsString('التقنية', $snapshot['content']);
        $this->assertStringContainsString('السعودية', $snapshot['content']);
        $this->assertStringContainsString('example.sa', $snapshot['content']);
        $this->assertStringContainsString('@example', $snapshot['content']);
        $this->assertStringContainsString('منافس أ', $snapshot['content']);
        $this->assertStringContainsString('زيادة المبيعات', $snapshot['content']);
        $this->assertStringContainsString('حل آمن', $snapshot['content']);
        $this->assertStringContainsString('أحدث فرصة نمو', $snapshot['content']);
        $this->assertStringNotContainsString('فرصة نمو واضحة', $snapshot['content']);
        $this->assertStringContainsString('أحدث إجراء فقط', $snapshot['content']);
        $this->assertStringNotContainsString('ملخص قديم لا يجب تحميله', $snapshot['content']);
        $this->assertStringContainsString('offer.multi~1line~u000A~0key.items.0.name: "عنصر"', $snapshot['content']);
        $this->assertStringContainsString('offer.multi~1line~u000A~0key.items.1: "1"', $snapshot['content']);
        $this->assertStringContainsString('offer.multi~1line~u000A~0key.items.2: 1', $snapshot['content']);
        $this->assertStringContainsString('offer.multi~1line~u000A~0key.items.3: null', $snapshot['content']);
        $this->assertStringContainsString('offer.multi~1line~u000A~0key.items.4: 1.5', $snapshot['content']);

        foreach ([
            'SECRET-123', 'TOOL-SECRET', 'WORKSPACE-SECRET', 'AUTHORIZATION-SECRET',
            'PRIVATE-KEY-SECRET', 'COOKIE-SECRET', 'ACCESS-KEY-SECRET', 'TOKEN-SECRET',
            'URL-SECRET', 'SIGNATURE-SECRET', 'QUERY-SIGNATURE-SECRET',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $snapshot['content']);
        }
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
