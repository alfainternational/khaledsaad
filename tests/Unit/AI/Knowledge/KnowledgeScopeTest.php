<?php

namespace Tests\Unit\AI\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class KnowledgeScopeTest extends TestCase
{
    #[Test]
    public function it_creates_a_project_scope(): void
    {
        $scope = KnowledgeScope::forProject(10, 20, 30);

        $this->assertSame(10, $scope->accountId);
        $this->assertSame(20, $scope->workspaceId);
        $this->assertSame(30, $scope->projectId);
        $this->assertSame('project', $scope->visibility);
        $this->assertSame(hash('sha256', 'project|10|20|30'), $scope->key());
    }

    #[Test]
    public function it_creates_a_deterministic_global_scope(): void
    {
        $scope = KnowledgeScope::global();

        $this->assertNull($scope->accountId);
        $this->assertNull($scope->workspaceId);
        $this->assertNull($scope->projectId);
        $this->assertSame('global', $scope->visibility);
        $this->assertSame(hash('sha256', 'global|global|global|global'), $scope->key());
        $this->assertSame($scope->key(), KnowledgeScope::global()->key());
    }

    #[Test]
    public function it_creates_a_deterministic_workspace_scope(): void
    {
        $scope = KnowledgeScope::forWorkspace(10, 20);

        $this->assertSame(10, $scope->accountId);
        $this->assertSame(20, $scope->workspaceId);
        $this->assertNull($scope->projectId);
        $this->assertSame('workspace', $scope->visibility);
        $this->assertSame(hash('sha256', 'workspace|10|20|global'), $scope->key());
        $this->assertSame($scope->key(), KnowledgeScope::forWorkspace(10, 20)->key());
    }

    #[Test]
    #[DataProvider('nonPositiveTenantIds')]
    public function it_rejects_non_positive_present_tenant_ids(
        ?int $accountId,
        ?int $workspaceId,
        ?int $projectId,
        string $visibility,
    ): void {
        $this->expectException(InvalidArgumentException::class);

        new KnowledgeScope($accountId, $workspaceId, $projectId, $visibility);
    }

    public static function nonPositiveTenantIds(): array
    {
        return [
            'zero project account' => [0, 20, 30, 'project'],
            'negative project account' => [-1, 20, 30, 'project'],
            'zero project workspace' => [10, 0, 30, 'project'],
            'negative project workspace' => [10, -1, 30, 'project'],
            'zero project id' => [10, 20, 0, 'project'],
            'negative project id' => [10, 20, -1, 'project'],
            'zero workspace account' => [0, 20, null, 'workspace'],
            'negative workspace account' => [-1, 20, null, 'workspace'],
            'zero workspace id' => [10, 0, null, 'workspace'],
            'negative workspace id' => [10, -1, null, 'workspace'],
        ];
    }

    #[Test]
    public function project_scope_rejects_missing_parents(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new KnowledgeScope(null, 20, 30, 'project');
    }

    #[Test]
    public function workspace_scope_rejects_a_project(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new KnowledgeScope(10, 20, 30, 'workspace');
    }

    #[Test]
    public function it_rejects_unknown_visibility(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new KnowledgeScope(10, 20, null, 'account');
    }
}
