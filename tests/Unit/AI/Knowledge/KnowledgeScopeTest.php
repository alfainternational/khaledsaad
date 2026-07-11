<?php

namespace Tests\Unit\AI\Knowledge;

use App\Domain\AI\Knowledge\KnowledgeScope;
use InvalidArgumentException;
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
