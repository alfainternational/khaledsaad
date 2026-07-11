# Knowledge Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the file-only AI memory with an isolated, traceable MariaDB knowledge foundation that can ingest project data and import the existing JSON knowledge without breaking current consumers.

**Architecture:** Laravel remains the source of truth. New Eloquent records store sources, documents, chunks, claims, evidence, reviews, and jobs with account/workspace/project scope; a repository owns deduplication and writes. `KnowledgeStore` keeps its current API during migration and mirrors compatible writes into the new repository behind a feature flag.

**Tech Stack:** Laravel 13, PHP 8.3+, MariaDB/MySQL, Eloquent, database queue, PHPUnit 12.

---

## Delivery Boundary

This plan is the first independently deployable subsystem from the approved design. It includes structured storage, tenant isolation, legacy import, project snapshot ingestion, compatibility, and health metrics. It intentionally excludes uploads, OCR, private-worker transport, embeddings, hybrid reranking, and knowledge UI; each receives a separate implementation plan after this foundation is deployed and measured.

## File Map

- Create `database/migrations/2026_07_11_120000_create_knowledge_foundation_tables.php`: all phase-one tables and indexes.
- Create `app/Domain/AI/Knowledge/Models/KnowledgeSource.php`: source ownership and trust metadata.
- Create `app/Domain/AI/Knowledge/Models/KnowledgeDocument.php`: versioned source material.
- Create `app/Domain/AI/Knowledge/Models/KnowledgeChunk.php`: searchable text units.
- Create `app/Domain/AI/Knowledge/KnowledgeScope.php`: immutable tenant scope value object.
- Create `app/Domain/AI/Knowledge/StructuredKnowledgeRepository.php`: idempotent write/read boundary.
- Create `app/Domain/AI/Knowledge/ProjectKnowledgeSnapshotBuilder.php`: canonical project document builder.
- Create `app/Console/Commands/ImportLegacyKnowledgeCommand.php`: repeatable JSON import.
- Create `app/Console/Commands/SyncProjectKnowledgeCommand.php`: project snapshot ingestion.
- Modify `app/Domain/AI/Kernel/Knowledge/KnowledgeStore.php`: optional dual-write compatibility.
- Modify `config/services.php`: rollout flags and trust defaults.
- Modify `routes/console.php`: schedules for project synchronization only after command tests pass.
- Test under `tests/Feature/AI/Knowledge/` and `tests/Unit/AI/Knowledge/`.

### Task 1: Create the knowledge schema

**Files:**
- Create: `database/migrations/2026_07_11_120000_create_knowledge_foundation_tables.php`
- Create: `tests/Feature/AI/Knowledge/KnowledgeFoundationSchemaTest.php`

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\AI\Knowledge;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeFoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function knowledge_tables_expose_the_required_contract(): void
    {
        $expected = [
            'knowledge_sources' => ['account_id', 'workspace_id', 'project_id', 'scope_key', 'kind', 'canonical_uri', 'trust_score'],
            'knowledge_documents' => ['knowledge_source_id', 'content_hash', 'status', 'version', 'valid_until'],
            'knowledge_chunks' => ['knowledge_document_id', 'position', 'content', 'token_count'],
            'knowledge_claims' => ['workspace_id', 'project_id', 'scope_key', 'statement', 'confidence', 'review_status'],
            'knowledge_evidence' => ['knowledge_claim_id', 'knowledge_chunk_id', 'relation', 'quote'],
            'knowledge_reviews' => ['knowledge_claim_id', 'reviewer_user_id', 'decision', 'reason'],
            'intelligence_jobs' => ['workspace_id', 'project_id', 'type', 'status', 'payload_json', 'attempts'],
        ];

        foreach ($expected as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), $table.' is missing');
            $this->assertTrue(Schema::hasColumns($table, $columns), $table.' contract is incomplete');
        }
    }
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run: `php artisan test tests/Feature/AI/Knowledge/KnowledgeFoundationSchemaTest.php`

Expected: FAIL because `knowledge_sources` does not exist.

- [ ] **Step 3: Add the migration**

Implement seven tables with these exact invariants:

```php
Schema::create('knowledge_sources', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
    $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
    $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
    $table->char('scope_key', 64);
    $table->string('kind', 40);
    $table->text('canonical_uri')->nullable();
    $table->char('identity_hash', 64);
    $table->unsignedTinyInteger('trust_score')->default(50);
    $table->string('visibility', 20)->default('project');
    $table->json('meta_json')->nullable();
    $table->timestamps();
    $table->unique(['scope_key', 'identity_hash'], 'knowledge_sources_scope_identity_unique');
    $table->index(['project_id', 'kind']);
});

Schema::create('knowledge_documents', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('knowledge_source_id')->constrained()->cascadeOnDelete();
    $table->char('content_hash', 64);
    $table->unsignedInteger('version')->default(1);
    $table->string('title')->nullable();
    $table->string('language', 12)->default('ar');
    $table->string('status', 24)->default('pending');
    $table->longText('content')->nullable();
    $table->timestamp('valid_from')->nullable();
    $table->timestamp('valid_until')->nullable();
    $table->json('meta_json')->nullable();
    $table->timestamps();
    $table->unique(['knowledge_source_id', 'content_hash']);
    $table->index(['knowledge_source_id', 'status', 'version']);
});

Schema::create('knowledge_chunks', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('knowledge_document_id')->constrained()->cascadeOnDelete();
    $table->unsignedInteger('position');
    $table->string('heading')->nullable();
    $table->longText('content');
    $table->unsignedInteger('token_count')->default(0);
    $table->json('locator_json')->nullable();
    $table->timestamps();
    $table->unique(['knowledge_document_id', 'position']);
});

Schema::create('knowledge_claims', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('account_id')->nullable()->constrained('accounts')->cascadeOnDelete();
    $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
    $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
    $table->char('scope_key', 64);
    $table->text('statement');
    $table->char('statement_hash', 64);
    $table->string('claim_type', 32)->default('fact');
    $table->decimal('confidence', 5, 2)->default(0);
    $table->string('review_status', 24)->default('candidate');
    $table->timestamp('valid_from')->nullable();
    $table->timestamp('valid_until')->nullable();
    $table->json('meta_json')->nullable();
    $table->timestamps();
    $table->unique(['scope_key', 'statement_hash'], 'knowledge_claims_scope_statement_unique');
    $table->index(['project_id', 'review_status']);
});

Schema::create('knowledge_evidence', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('knowledge_claim_id')->constrained()->cascadeOnDelete();
    $table->foreignId('knowledge_chunk_id')->constrained()->cascadeOnDelete();
    $table->string('relation', 20)->default('supports');
    $table->text('quote')->nullable();
    $table->json('locator_json')->nullable();
    $table->timestamps();
    $table->unique(['knowledge_claim_id', 'knowledge_chunk_id', 'relation'], 'knowledge_evidence_unique');
});

Schema::create('knowledge_reviews', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('knowledge_claim_id')->constrained()->cascadeOnDelete();
    $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('decision', 20);
    $table->text('reason')->nullable();
    $table->timestamps();
    $table->index(['knowledge_claim_id', 'created_at']);
});

Schema::create('intelligence_jobs', function (Blueprint $table): void {
    $table->id();
    $table->uuid('public_id')->unique();
    $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->cascadeOnDelete();
    $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
    $table->string('type', 48);
    $table->string('status', 24)->default('queued');
    $table->json('payload_json');
    $table->json('result_json')->nullable();
    $table->unsignedTinyInteger('attempts')->default(0);
    $table->timestamp('available_at')->nullable();
    $table->timestamp('leased_until')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->text('last_error')->nullable();
    $table->timestamps();
    $table->index(['status', 'available_at']);
    $table->index(['workspace_id', 'project_id', 'type']);
});

if (DB::getDriverName() === 'mysql') {
    Schema::table('knowledge_chunks', function (Blueprint $table): void {
        $table->fullText('content', 'knowledge_chunks_content_fulltext');
    });
}
```

The migration `down()` drops tables in reverse dependency order.

- [ ] **Step 4: Run the schema test**

Run: `php artisan test tests/Feature/AI/Knowledge/KnowledgeFoundationSchemaTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_11_120000_create_knowledge_foundation_tables.php tests/Feature/AI/Knowledge/KnowledgeFoundationSchemaTest.php
git commit -m "feat: add structured knowledge schema"
```

### Task 2: Add tenant scope and core models

**Files:**
- Create: `app/Domain/AI/Knowledge/KnowledgeScope.php`
- Create: `app/Domain/AI/Knowledge/Models/KnowledgeSource.php`
- Create: `app/Domain/AI/Knowledge/Models/KnowledgeDocument.php`
- Create: `app/Domain/AI/Knowledge/Models/KnowledgeChunk.php`
- Create: `tests/Unit/AI/Knowledge/KnowledgeScopeTest.php`
- Create: `tests/Feature/AI/Knowledge/KnowledgeModelIsolationTest.php`

- [ ] **Step 1: Write failing scope tests**

```php
#[Test]
public function project_scope_requires_matching_workspace_and_account(): void
{
    $scope = KnowledgeScope::forProject(accountId: 4, workspaceId: 8, projectId: 15);

    $this->assertSame(4, $scope->accountId);
    $this->assertSame(8, $scope->workspaceId);
    $this->assertSame(15, $scope->projectId);
    $this->assertSame('project', $scope->visibility);
}

#[Test]
public function project_scope_rejects_missing_parent_ids(): void
{
    $this->expectException(InvalidArgumentException::class);
    new KnowledgeScope(accountId: null, workspaceId: 8, projectId: 15, visibility: 'project');
}
```

The feature test creates two workspaces, stores one source under each, applies `KnowledgeSource::inScope($scope)`, and asserts that only the matching source is returned.

- [ ] **Step 2: Run tests and verify they fail**

Run: `php artisan test tests/Unit/AI/Knowledge/KnowledgeScopeTest.php tests/Feature/AI/Knowledge/KnowledgeModelIsolationTest.php`

Expected: FAIL because `KnowledgeScope` and models do not exist.

- [ ] **Step 3: Implement the immutable scope**

```php
final readonly class KnowledgeScope
{
    public function __construct(
        public ?int $accountId,
        public ?int $workspaceId,
        public ?int $projectId,
        public string $visibility,
    ) {
        if ($visibility === 'project' && ($accountId === null || $workspaceId === null || $projectId === null)) {
            throw new InvalidArgumentException('Project knowledge requires account, workspace, and project ids.');
        }
        if ($visibility === 'workspace' && ($accountId === null || $workspaceId === null || $projectId !== null)) {
            throw new InvalidArgumentException('Workspace knowledge requires account and workspace only.');
        }
        if ($visibility === 'global' && ($accountId !== null || $workspaceId !== null || $projectId !== null)) {
            throw new InvalidArgumentException('Global knowledge cannot carry tenant ids.');
        }
    }

    public static function forProject(int $accountId, int $workspaceId, int $projectId): self
    {
        return new self($accountId, $workspaceId, $projectId, 'project');
    }

    public static function global(): self
    {
        return new self(null, null, null, 'global');
    }

    public function key(): string
    {
        return hash('sha256', implode('|', [
            $this->visibility,
            $this->accountId ?? 'global',
            $this->workspaceId ?? 'global',
            $this->projectId ?? 'global',
        ]));
    }
}
```

- [ ] **Step 4: Implement the core model relations and casts**

`KnowledgeSource` owns documents and exposes this exact scope:

```php
public function scopeInScope(Builder $query, KnowledgeScope $scope): Builder
{
    return $query
        ->where('visibility', $scope->visibility)
        ->where('account_id', $scope->accountId)
        ->where('workspace_id', $scope->workspaceId)
        ->where('project_id', $scope->projectId);
}

public function documents(): HasMany
{
    return $this->hasMany(KnowledgeDocument::class);
}
```

`KnowledgeDocument` belongs to a source and has many chunks. `KnowledgeChunk` belongs to a document. Add fillable fields matching the migration and casts for JSON, timestamps, integers, and trust score.

- [ ] **Step 5: Run tests**

Run: `php artisan test tests/Unit/AI/Knowledge/KnowledgeScopeTest.php tests/Feature/AI/Knowledge/KnowledgeModelIsolationTest.php`

Expected: PASS, including cross-workspace isolation.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/AI/Knowledge/KnowledgeScope.php app/Domain/AI/Knowledge/Models tests/Unit/AI/Knowledge tests/Feature/AI/Knowledge/KnowledgeModelIsolationTest.php
git commit -m "feat: add isolated knowledge models"
```

### Task 3: Build the idempotent structured repository

**Files:**
- Create: `app/Domain/AI/Knowledge/StructuredKnowledgeRepository.php`
- Create: `tests/Feature/AI/Knowledge/StructuredKnowledgeRepositoryTest.php`

- [ ] **Step 1: Write failing repository tests**

Cover these exact behaviors:

```php
$first = $repository->storeDocument(
    scope: $scope,
    kind: 'project_snapshot',
    canonicalUri: 'project://15/profile',
    title: 'Project profile',
    content: 'Original project content',
    chunks: [['heading' => 'Profile', 'content' => 'Original project content', 'locator' => ['field' => 'profile']]],
    trustScore: 100,
);
$same = $repository->storeDocument(
    scope: $scope,
    kind: 'project_snapshot',
    canonicalUri: 'project://15/profile',
    title: 'Project profile',
    content: 'Original project content',
    chunks: [['heading' => 'Profile', 'content' => 'Original project content', 'locator' => ['field' => 'profile']]],
    trustScore: 100,
);

$this->assertTrue($first->is($same));
$this->assertDatabaseCount('knowledge_documents', 1);
$this->assertDatabaseCount('knowledge_chunks', 1);
```

A second test changes content and asserts document version `2`, two document rows, and only the latest document returned by `latestDocument()`.

- [ ] **Step 2: Run test and verify it fails**

Run: `php artisan test tests/Feature/AI/Knowledge/StructuredKnowledgeRepositoryTest.php`

Expected: FAIL because the repository does not exist.

- [ ] **Step 3: Implement deterministic identities**

Use these formulas:

```php
$identityHash = hash('sha256', implode('|', [
    $scope->visibility,
    $scope->accountId ?? 'global',
    $scope->workspaceId ?? 'global',
    $scope->projectId ?? 'global',
    $kind,
    trim($canonicalUri),
]));
$scopeKey = $scope->key();
$contentHash = hash('sha256', $content);
```

Wrap writes in `DB::transaction()`. Use `firstOrCreate` for the scoped source and source/content hash pair. Lock the source row while selecting the next version. Create chunk positions from array order and estimate token count with `max(1, (int) ceil(mb_strlen($content) / 4))`.

- [ ] **Step 4: Implement read methods**

Add signatures:

```php
public function storeDocument(KnowledgeScope $scope, string $kind, string $canonicalUri, string $title, string $content, array $chunks, int $trustScore = 50): KnowledgeDocument;
public function latestDocument(KnowledgeScope $scope, string $kind, string $canonicalUri): ?KnowledgeDocument;
public function searchText(KnowledgeScope $scope, string $query, int $limit = 10): Collection;
```

`searchText()` scopes through `knowledge_sources`, searches `knowledge_chunks.content` with `MATCH (knowledge_chunks.content) AGAINST (? IN NATURAL LANGUAGE MODE)` on MySQL/MariaDB, and uses escaped `LIKE` only when the test database is SQLite.

- [ ] **Step 5: Run tests**

Run: `php artisan test tests/Feature/AI/Knowledge/StructuredKnowledgeRepositoryTest.php`

Expected: PASS for deduplication, versioning, isolation, and retrieval.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/AI/Knowledge/StructuredKnowledgeRepository.php tests/Feature/AI/Knowledge/StructuredKnowledgeRepositoryTest.php
git commit -m "feat: add idempotent knowledge repository"
```

### Task 4: Add legacy JSON import

**Files:**
- Create: `app/Console/Commands/ImportLegacyKnowledgeCommand.php`
- Create: `tests/Feature/AI/Knowledge/ImportLegacyKnowledgeCommandTest.php`

- [ ] **Step 1: Write the failing command test**

```php
Storage::fake('local');
Storage::disk('local')->put('ai-knowledge/playbook.offer.json', json_encode([
    'key' => 'playbook.offer',
    'data' => [
        'principles' => ['اربط الوعد بدليل'],
        'quick_win' => 'أضف الدليل بجانب الدعوة للإجراء',
    ],
    'learned_at' => '2026-07-01T03:00:00+03:00',
], JSON_UNESCAPED_UNICODE));

$this->artisan('knowledge:import-legacy')->assertSuccessful();
$this->artisan('knowledge:import-legacy')->assertSuccessful();

$this->assertDatabaseCount('knowledge_sources', 1);
$this->assertDatabaseCount('knowledge_documents', 1);
$this->assertDatabaseHas('knowledge_chunks', ['position' => 0]);
```

- [ ] **Step 2: Run and verify failure**

Run: `php artisan test tests/Feature/AI/Knowledge/ImportLegacyKnowledgeCommandTest.php`

Expected: FAIL because `knowledge:import-legacy` is undefined.

- [ ] **Step 3: Implement canonical legacy text conversion**

The command iterates `Storage::disk('local')->files('ai-knowledge')`, validates JSON, and converts nested scalar data into deterministic lines using dot paths:

```php
private function flatten(array $data, string $prefix = ''): array
{
    $lines = [];
    foreach ($data as $key => $value) {
        $path = ltrim($prefix.'.'.$key, '.');
        if (is_array($value)) {
            $lines = array_merge($lines, $this->flatten($value, $path));
        } elseif (is_scalar($value) || $value === null) {
            $lines[] = $path.': '.($value === null ? 'null' : (string) $value);
        }
    }
    return $lines;
}
```

Store each file with `KnowledgeScope::global()`, `kind=legacy_memory`, URI `legacy://{key}`, trust score `50`, and one chunk. Invalid files increment `skipped` and never stop the run. Print `Imported: N; unchanged: N; skipped: N`.

- [ ] **Step 4: Run tests**

Run: `php artisan test tests/Feature/AI/Knowledge/ImportLegacyKnowledgeCommandTest.php`

Expected: PASS and second execution creates no duplicates.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ImportLegacyKnowledgeCommand.php tests/Feature/AI/Knowledge/ImportLegacyKnowledgeCommandTest.php
git commit -m "feat: import legacy AI knowledge"
```

### Task 5: Ingest versioned project snapshots

**Files:**
- Create: `app/Domain/AI/Knowledge/ProjectKnowledgeSnapshotBuilder.php`
- Create: `app/Console/Commands/SyncProjectKnowledgeCommand.php`
- Create: `tests/Unit/AI/Knowledge/ProjectKnowledgeSnapshotBuilderTest.php`
- Create: `tests/Feature/AI/Knowledge/SyncProjectKnowledgeCommandTest.php`

- [ ] **Step 1: Write failing snapshot tests**

The unit test constructs a project with sector, country, domain, social profiles, competitors, and goals. Assert the builder returns ordered chunks with headings `Project`, `Market`, `Channels`, `Competitors`, and `Goals`, and that no API key or unrelated workspace data appears.

The feature test executes:

```php
$this->artisan('knowledge:sync-projects', ['--project' => $project->id])
    ->expectsOutputToContain('Synced: 1')
    ->assertSuccessful();

$this->assertDatabaseHas('knowledge_sources', [
    'workspace_id' => $workspace->id,
    'project_id' => $project->id,
    'kind' => 'project_snapshot',
    'trust_score' => 100,
]);
```

- [ ] **Step 2: Run and verify failure**

Run: `php artisan test tests/Unit/AI/Knowledge/ProjectKnowledgeSnapshotBuilderTest.php tests/Feature/AI/Knowledge/SyncProjectKnowledgeCommandTest.php`

Expected: FAIL because builder and command do not exist.

- [ ] **Step 3: Implement the snapshot builder**

Return this contract:

```php
/** @return array{title: string, content: string, chunks: array<int, array{heading: string, content: string, locator: array<string, string>}>} */
public function build(Project $project): array;
```

Only include explicit project fields and related marketing brief/tool summary data already authorized for the same project. Normalize line endings, sort associative keys, omit blank values, and use field names in each locator.

- [ ] **Step 4: Implement the command**

`knowledge:sync-projects {--project=}` processes projects with `chunkById(25)` and builds the scope exactly as follows:

```php
$scope = KnowledgeScope::forProject(
    accountId: (int) $project->workspace->account_id,
    workspaceId: (int) $project->workspace_id,
    projectId: (int) $project->id,
);
```

Store URI `project://{$project->public_id}/snapshot`. Print synced, unchanged, and failed counts; catch errors per project so one bad record does not abort the batch.

- [ ] **Step 5: Run tests**

Run: `php artisan test tests/Unit/AI/Knowledge/ProjectKnowledgeSnapshotBuilderTest.php tests/Feature/AI/Knowledge/SyncProjectKnowledgeCommandTest.php`

Expected: PASS, and a repeated command creates no new version until project content changes.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/AI/Knowledge/ProjectKnowledgeSnapshotBuilder.php app/Console/Commands/SyncProjectKnowledgeCommand.php tests/Unit/AI/Knowledge/ProjectKnowledgeSnapshotBuilderTest.php tests/Feature/AI/Knowledge/SyncProjectKnowledgeCommandTest.php
git commit -m "feat: sync project knowledge snapshots"
```

### Task 6: Add safe dual-write compatibility

**Files:**
- Modify: `app/Domain/AI/Kernel/Knowledge/KnowledgeStore.php`
- Modify: `config/services.php`
- Create: `tests/Feature/AI/Knowledge/KnowledgeStoreCompatibilityTest.php`

- [ ] **Step 1: Write failing compatibility tests**

Test three modes:

1. Flag off: JSON behavior remains unchanged and no structured row is written.
2. Flag on with valid global payload: JSON and structured document both exist.
3. Structured write throws: JSON write succeeds and a warning is logged without exposing payload content.

- [ ] **Step 2: Run and verify failure**

Run: `php artisan test tests/Feature/AI/Knowledge/KnowledgeStoreCompatibilityTest.php`

Expected: FAIL because dual write is not implemented.

- [ ] **Step 3: Add rollout configuration**

```php
'knowledge' => [
    'structured_store' => env('AI_KNOWLEDGE_STRUCTURED_STORE', false),
    'dual_write' => env('AI_KNOWLEDGE_DUAL_WRITE', false),
    'project_sync' => env('AI_KNOWLEDGE_PROJECT_SYNC', false),
],
```

- [ ] **Step 4: Add non-breaking dual write**

Inject `StructuredKnowledgeRepository` optionally through the constructor. Keep `remember`, `recall`, `all`, and `forget` signatures unchanged. After the existing JSON write, mirror a global `legacy_memory` document only when both structured-store and dual-write flags are true. Catch `Throwable`, log key plus exception class, and never log the data body.

- [ ] **Step 5: Run compatibility and existing semantic tests**

Run: `php artisan test tests/Feature/AI/Knowledge/KnowledgeStoreCompatibilityTest.php tests/Unit/AI/SemanticUnderstandingTest.php`

Expected: PASS; existing consumers still read JSON successfully.

- [ ] **Step 6: Commit**

```bash
git add app/Domain/AI/Kernel/Knowledge/KnowledgeStore.php config/services.php tests/Feature/AI/Knowledge/KnowledgeStoreCompatibilityTest.php
git commit -m "feat: dual write legacy knowledge safely"
```

### Task 7: Add health reporting and production schedule

**Files:**
- Create: `app/Console/Commands/KnowledgeHealthCommand.php`
- Modify: `routes/console.php`
- Create: `tests/Feature/AI/Knowledge/KnowledgeHealthCommandTest.php`
- Modify: `tests/Feature/Intelligence/MonitoringScheduleTest.php`

- [ ] **Step 1: Write failing health and schedule tests**

Assert `knowledge:health --json` returns keys `sources`, `documents`, `chunks`, `candidate_claims`, `failed_jobs`, and `stale_documents`. Assert `knowledge:sync-projects` appears daily only when `services.knowledge.project_sync` is true and uses `withoutOverlapping()`.

- [ ] **Step 2: Run and verify failure**

Run: `php artisan test tests/Feature/AI/Knowledge/KnowledgeHealthCommandTest.php tests/Feature/Intelligence/MonitoringScheduleTest.php`

Expected: FAIL because command and schedule entry do not exist.

- [ ] **Step 3: Implement read-only health aggregation**

Use count queries only. A stale document is active with `valid_until < now()`. A failed intelligence job has status `failed`. JSON output must be one valid object; text output prints one metric per line.

- [ ] **Step 4: Add the guarded schedule**

```php
if ((bool) config('services.knowledge.project_sync', false)) {
    Schedule::command('knowledge:sync-projects')
        ->dailyAt('03:15')
        ->withoutOverlapping()
        ->name('knowledge-project-sync');
}
```

- [ ] **Step 5: Run phase-one and regression tests**

Run:

```bash
php artisan test tests/Feature/AI/Knowledge tests/Unit/AI/Knowledge tests/Unit/AI/SemanticUnderstandingTest.php tests/Feature/Intelligence/MonitoringScheduleTest.php
php artisan test tests/Unit/AI tests/Unit/Agents tests/Feature/AI tests/Feature/Projects/ProjectIntelligenceAuditTest.php tests/Unit/RemotePageFetcherTest.php
```

Expected: all new tests pass and the established 94-test AI baseline remains green.

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/KnowledgeHealthCommand.php routes/console.php tests/Feature/AI/Knowledge/KnowledgeHealthCommandTest.php tests/Feature/Intelligence/MonitoringScheduleTest.php
git commit -m "feat: monitor knowledge foundation health"
```

### Task 8: Stage and verify on shared hosting

**Files:**
- Modify: `.env.production.example`
- Modify: `DEPLOYMENT.md`
- Create: `docs/platform/KNOWLEDGE_FOUNDATION_RUNBOOK.md`

- [ ] **Step 1: Document exact rollout flags**

Add disabled defaults:

```dotenv
AI_KNOWLEDGE_STRUCTURED_STORE=false
AI_KNOWLEDGE_DUAL_WRITE=false
AI_KNOWLEDGE_PROJECT_SYNC=false
```

The runbook orders rollout: backup database, deploy code, migrate, import legacy, enable structured store, observe health, enable dual write, synchronize one project, compare counts/content, then enable project schedule.

- [ ] **Step 2: Verify migration on a production-shaped database**

Run locally:

```bash
php artisan migrate:fresh --seed --env=testing
php artisan knowledge:import-legacy
php artisan knowledge:sync-projects --project=1
php artisan knowledge:health --json
```

Expected: commands succeed, JSON parses, and no cross-project records appear.

- [ ] **Step 3: Run the complete Laravel test suite**

Run: `php artisan test`

Expected: PASS with no existing failures.

- [ ] **Step 4: Commit documentation**

```bash
git add .env.production.example DEPLOYMENT.md docs/platform/KNOWLEDGE_FOUNDATION_RUNBOOK.md
git commit -m "docs: add knowledge foundation rollout runbook"
```

- [ ] **Step 5: Deploy with flags disabled**

Use the existing `deploy/cpanel-push.sh` process to upload code and `vendor` changes if any. Run `php artisan migrate --force`, clear caches, and verify `php artisan knowledge:health --json` before changing flags.

- [ ] **Step 6: Enable one flag at a time and verify**

After each flag, run:

```bash
php artisan config:cache
php artisan knowledge:health --json
php artisan queue:failed
```

Expected: no new failed jobs, memory stays within the 128 MB PHP limit, and the public `/up` endpoint returns HTTP 200. Roll back the flag immediately if any invariant fails.

## Completion Gate

Phase one is complete only when the production database contains isolated structured knowledge, legacy import is idempotent, at least one real project snapshot is searchable, health output is clean, the full test suite passes, and all rollout flags can be disabled without breaking the existing JSON-based intelligence.
