# Diagnostic Foundation Rebuild Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the independently deployable diagnostic foundation that turns project inputs and existing tool results into traceable claims, evidence, diagnostic snapshots, five explicit scores, and a contract-readiness result without deleting legacy data.

**Architecture:** Add a bounded `Diagnosis` domain beside the current Project, Intelligence, and Execution domains. Deterministic services calculate status and scores from normalized claims and evidence; web/API presenters consume stored snapshots instead of recalculating in controllers. Legacy project data is imported idempotently and remains readable throughout rollout.

**Tech Stack:** Laravel 13, PHP 8.3+, Eloquent, MariaDB/MySQL, Blade, Sanctum API v1, PHPUnit 12.

---

## Delivery Boundary

This is phase 1 of the approved full rebuild. It delivers the new database foundation,
diagnostic engine, migration of current project facts, API read/write contracts, and a
web diagnostic matrix. Agency briefs, proposal comparison, full navigation redesign,
and Flutter screens receive subsequent plans after this contract is green.

## File Map

- Create `database/migrations/2026_07_11_210000_create_diagnostic_foundation_tables.php`: claims, evidence, links, snapshots, assessments, and scores.
- Create `app/Domain/Diagnosis/Models/*`: focused Eloquent records.
- Create `app/Domain/Diagnosis/Enums/*`: claim, evidence, assessment, and score vocabulary.
- Create `app/Domain/Diagnosis/Services/DiagnosticScoreCalculator.php`: deterministic score formulas.
- Create `app/Application/Diagnosis/CreateDiagnosticSnapshotAction.php`: transactional snapshot creation.
- Create `app/Application/Diagnosis/ImportLegacyProjectDiagnosisAction.php`: idempotent legacy mapping.
- Create `app/Console/Commands/ImportLegacyProjectDiagnosisCommand.php`: safe batch execution.
- Create `app/Http/Controllers/Api/V1/ProjectClaimController.php`: versioned mobile/API contract.
- Create `app/Http/Controllers/Api/V1/ProjectDiagnosisController.php`: snapshot API.
- Create `app/Http/Resources/Diagnosis/*`: stable response shape.
- Create `app/Http/Controllers/Web/ProjectDiagnosisController.php`: web matrix.
- Create `resources/views/app/projects/diagnosis.blade.php`: RTL diagnosis experience.
- Modify `routes/api.php`, `routes/web.php`, and the active project navigation partial.
- Test under `tests/Feature/Diagnosis`, `tests/Unit/Diagnosis`, and `tests/Feature/Api/V1`.

### Task 1: Create the diagnostic schema and enums

**Files:**
- Create: `tests/Feature/Diagnosis/DiagnosticSchemaTest.php`
- Create: `database/migrations/2026_07_11_210000_create_diagnostic_foundation_tables.php`
- Create: `app/Domain/Diagnosis/Enums/ClaimType.php`
- Create: `app/Domain/Diagnosis/Enums/ClaimStatus.php`
- Create: `app/Domain/Diagnosis/Enums/EvidenceVerification.php`
- Create: `app/Domain/Diagnosis/Enums/AssessmentStatus.php`
- Create: `app/Domain/Diagnosis/Enums/DiagnosticScoreType.php`

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\Diagnosis;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiagnosticSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function diagnostic_tables_expose_the_required_contract(): void
    {
        $contracts = [
            'project_claims' => ['workspace_id', 'project_id', 'type', 'status', 'subject', 'predicate', 'value_text', 'confidence', 'version'],
            'project_evidence' => ['workspace_id', 'project_id', 'type', 'verification_status', 'title', 'content_hash'],
            'claim_evidence' => ['project_claim_id', 'project_evidence_id', 'relation'],
            'diagnostic_snapshots' => ['workspace_id', 'project_id', 'status', 'rules_version', 'summary'],
            'diagnostic_assessments' => ['diagnostic_snapshot_id', 'axis', 'status', 'priority', 'confidence'],
            'diagnostic_scores' => ['diagnostic_snapshot_id', 'type', 'score', 'components_json'],
        ];

        foreach ($contracts as $table => $columns) {
            $this->assertTrue(Schema::hasTable($table), $table.' is missing');
            $this->assertTrue(Schema::hasColumns($table, $columns), $table.' is incomplete');
        }
    }
}
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php artisan test tests/Feature/Diagnosis/DiagnosticSchemaTest.php`

Expected: FAIL because `project_claims` does not exist.

- [ ] **Step 3: Add enums and migration**

Use string-backed enums with these exact values:

```php
enum ClaimType: string
{
    case UserInput = 'user_input';
    case VerifiedEvidence = 'verified_evidence';
    case SystemInference = 'system_inference';
    case Assumption = 'assumption';
    case Recommendation = 'recommendation';
}
```

The migration must use foreign keys to `workspaces`, `projects`, and `users`, soft
deletes on claims/evidence/snapshots, a unique `(project_id, identity_hash, version)`
claim key, and unique `(diagnostic_snapshot_id, type)` scores. Store files only by
private `storage_path`; never store public URLs.

- [ ] **Step 4: Run the schema test and full migrations**

Run: `php artisan test tests/Feature/Diagnosis/DiagnosticSchemaTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_11_210000_create_diagnostic_foundation_tables.php app/Domain/Diagnosis/Enums tests/Feature/Diagnosis/DiagnosticSchemaTest.php
git commit -m "feat: add diagnostic foundation schema"
```

### Task 2: Add tenant-safe claim and evidence models

**Files:**
- Create: `tests/Feature/Diagnosis/ClaimEvidenceIsolationTest.php`
- Create: `app/Domain/Diagnosis/Models/ProjectClaim.php`
- Create: `app/Domain/Diagnosis/Models/ProjectEvidence.php`
- Create: `app/Domain/Diagnosis/Models/DiagnosticSnapshot.php`
- Create: `app/Domain/Diagnosis/Models/DiagnosticAssessment.php`
- Create: `app/Domain/Diagnosis/Models/DiagnosticScore.php`
- Modify: `app/Domain/Project/Models/Project.php`

- [ ] **Step 1: Write failing relationship and isolation tests**

```php
#[Test]
public function evidence_from_another_project_cannot_be_attached_to_a_claim(): void
{
    [$workspace, $first, $second] = $this->workspaceWithTwoProjects();
    $claim = ProjectClaim::factory()->for($workspace)->for($first)->create();
    $evidence = ProjectEvidence::factory()->for($workspace)->for($second)->create();

    $this->expectException(DomainException::class);
    $claim->attachEvidence($evidence, 'supports');
}
```

Also assert enum casts, integer confidence, JSON value/components casts, project
relationships, and that snapshot scores are addressable by `DiagnosticScoreType`.

- [ ] **Step 2: Run the tests and verify RED**

Run: `php artisan test tests/Feature/Diagnosis/ClaimEvidenceIsolationTest.php`

Expected: FAIL because the models do not exist.

- [ ] **Step 3: Implement focused models**

`ProjectClaim::attachEvidence()` must compare both `workspace_id` and `project_id`
before inserting the pivot. Models use `$fillable`, enum casts, and explicit relations;
controllers must never call unrestricted `ProjectClaim::find()`.

- [ ] **Step 4: Run focused and project model tests**

Run: `php artisan test tests/Feature/Diagnosis/ClaimEvidenceIsolationTest.php tests/Unit/Domain/Project`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Diagnosis/Models app/Domain/Project/Models/Project.php tests/Feature/Diagnosis/ClaimEvidenceIsolationTest.php
git commit -m "feat: model diagnostic claims and evidence"
```

### Task 3: Implement deterministic diagnostic scoring

**Files:**
- Create: `tests/Unit/Diagnosis/DiagnosticScoreCalculatorTest.php`
- Create: `app/Domain/Diagnosis/Data/DiagnosticInput.php`
- Create: `app/Domain/Diagnosis/Data/DiagnosticScoreSet.php`
- Create: `app/Domain/Diagnosis/Services/DiagnosticScoreCalculator.php`

- [ ] **Step 1: Write failing score tests**

```php
#[Test]
public function it_returns_five_named_scores_and_penalizes_missing_or_conflicting_evidence(): void
{
    $calculator = new DiagnosticScoreCalculator;
    $complete = $calculator->calculate(DiagnosticInput::fake(
        requiredFields: 10, answeredFields: 10, confirmedClaims: 8,
        evidencedClaims: 6, conflictingClaims: 0, staleEvidence: 0,
    ));
    $weak = $calculator->calculate(DiagnosticInput::fake(
        requiredFields: 10, answeredFields: 6, confirmedClaims: 3,
        evidencedClaims: 1, conflictingClaims: 2, staleEvidence: 1,
    ));

    $this->assertSame([
        'data_completeness', 'input_quality', 'evidence_strength',
        'diagnostic_confidence', 'contract_readiness',
    ], array_keys($complete->toArray()));
    $this->assertGreaterThan($weak->diagnosticConfidence, $complete->diagnosticConfidence);
    $this->assertLessThanOrEqual(100, $complete->contractReadiness);
}
```

- [ ] **Step 2: Run and verify RED**

Run: `php artisan test tests/Unit/Diagnosis/DiagnosticScoreCalculatorTest.php`

Expected: FAIL because `DiagnosticScoreCalculator` is missing.

- [ ] **Step 3: Implement the formulas**

Use integer arithmetic and clamp every result to 0..100:

```php
$dataCompleteness = ratio($answeredFields, $requiredFields);
$inputQuality = clamp(ratio($confirmedClaims, max(1, $answeredFields)) - ($conflictingClaims * 10));
$evidenceStrength = clamp(ratio($evidencedClaims, max(1, $confirmedClaims)) - ($staleEvidence * 5));
$diagnosticConfidence = weighted([$dataCompleteness => 30, $inputQuality => 30, $evidenceStrength => 40]);
$contractReadiness = weighted([$dataCompleteness => 25, $evidenceStrength => 25, $diagnosticConfidence => 30, $scopeReadiness => 20]);
```

Return components used for every score so the UI can explain it. Do not use an AI
response in any formula.

- [ ] **Step 4: Run tests and verify GREEN**

Run: `php artisan test tests/Unit/Diagnosis/DiagnosticScoreCalculatorTest.php`

Expected: PASS with boundary cases for zero required fields and scores above/below bounds.

- [ ] **Step 5: Commit**

```bash
git add app/Domain/Diagnosis/Data app/Domain/Diagnosis/Services tests/Unit/Diagnosis
git commit -m "feat: calculate explicit diagnostic scores"
```

### Task 4: Create versioned diagnostic snapshots

**Files:**
- Create: `tests/Feature/Diagnosis/CreateDiagnosticSnapshotTest.php`
- Create: `app/Application/Diagnosis/CreateDiagnosticSnapshotAction.php`
- Create: `app/Domain/Diagnosis/Services/DiagnosticAxisEvaluator.php`

- [ ] **Step 1: Write failing snapshot tests**

Test that the action creates one immutable snapshot, nine named assessments, and five
scores in one transaction. A project with insufficient evidence must receive `unknown`
for the affected axis rather than a generic positive recommendation.

```php
$snapshot = app(CreateDiagnosticSnapshotAction::class)->handle($project, $user);

$this->assertCount(9, $snapshot->assessments);
$this->assertCount(5, $snapshot->scores);
$this->assertSame('unknown', $snapshot->assessments->firstWhere('axis', 'pricing_economics')->status->value);
```

- [ ] **Step 2: Run and verify RED**

Run: `php artisan test tests/Feature/Diagnosis/CreateDiagnosticSnapshotTest.php`

Expected: FAIL because the action is missing.

- [ ] **Step 3: Implement snapshot creation**

Lock the project row, read active claims/evidence once, evaluate all axes, calculate
scores, and persist the graph in `DB::transaction()`. Set `rules_version` to a config
value. Never mutate a ready snapshot; a recalculation creates a new row.

- [ ] **Step 4: Run tests and verify GREEN**

Run: `php artisan test tests/Feature/Diagnosis/CreateDiagnosticSnapshotTest.php`

Expected: PASS, including rollback when an assessment insert fails.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Diagnosis app/Domain/Diagnosis/Services/DiagnosticAxisEvaluator.php tests/Feature/Diagnosis/CreateDiagnosticSnapshotTest.php
git commit -m "feat: create immutable diagnostic snapshots"
```

### Task 5: Import legacy project information idempotently

**Files:**
- Create: `tests/Feature/Diagnosis/ImportLegacyProjectDiagnosisTest.php`
- Create: `app/Application/Diagnosis/ImportLegacyProjectDiagnosisAction.php`
- Create: `app/Console/Commands/ImportLegacyProjectDiagnosisCommand.php`
- Modify: `routes/console.php`

- [ ] **Step 1: Write failing import tests**

Seed a project with brief fields, completed tool runs, audit findings, recommendations,
and execution packages. Assert the importer creates typed claims/evidence with legacy
IDs, and a second run creates zero duplicates.

```php
$first = $action->handle($project);
$second = $action->handle($project->fresh());

$this->assertGreaterThan(0, $first->createdClaims);
$this->assertSame(0, $second->createdClaims);
$this->assertDatabaseHas('project_claims', [
    'project_id' => $project->id,
    'legacy_source_type' => 'tool_run',
]);
```

- [ ] **Step 2: Run and verify RED**

Run: `php artisan test tests/Feature/Diagnosis/ImportLegacyProjectDiagnosisTest.php`

Expected: FAIL because the importer is missing.

- [ ] **Step 3: Implement import and command**

Generate `identity_hash` from project, source type, source ID, normalized predicate,
and normalized value. Use `firstOrCreate`, process projects with `chunkById(50)`, and
support `--project=`, `--dry-run`, and `--force`. Dry-run may read but must not write.

- [ ] **Step 4: Verify import, dry-run, and idempotency**

Run: `php artisan test tests/Feature/Diagnosis/ImportLegacyProjectDiagnosisTest.php`

Expected: PASS with unchanged row counts after the second run.

- [ ] **Step 5: Commit**

```bash
git add app/Application/Diagnosis/ImportLegacyProjectDiagnosisAction.php app/Console/Commands/ImportLegacyProjectDiagnosisCommand.php routes/console.php tests/Feature/Diagnosis/ImportLegacyProjectDiagnosisTest.php
git commit -m "feat: import legacy project diagnosis data"
```

### Task 6: Expose claims and diagnosis through API v1

**Files:**
- Create: `tests/Feature/Api/V1/ProjectDiagnosisApiTest.php`
- Create: `app/Http/Controllers/Api/V1/ProjectClaimController.php`
- Create: `app/Http/Controllers/Api/V1/ProjectDiagnosisController.php`
- Create: `app/Http/Requests/Api/V1/StoreProjectClaimRequest.php`
- Create: `app/Http/Resources/Diagnosis/ProjectClaimResource.php`
- Create: `app/Http/Resources/Diagnosis/DiagnosticSnapshotResource.php`
- Modify: `routes/api.php`

- [ ] **Step 1: Write failing API contract tests**

Assert authenticated workspace members can list/create claims and request/read a
snapshot, outsiders receive 404, invalid enum values receive 422 in Arabic, and stale
`version` updates receive 409. Assert the response envelope contains `data`, `meta`,
and `errors`, with `meta.contract_version = 1`.

- [ ] **Step 2: Run and verify RED**

Run: `php artisan test tests/Feature/Api/V1/ProjectDiagnosisApiTest.php`

Expected: FAIL with route not found.

- [ ] **Step 3: Implement the API**

Add inside the existing `api.workspace` and `api.project` middleware group:

```php
Route::get('/claims', [ProjectClaimController::class, 'index']);
Route::post('/claims', [ProjectClaimController::class, 'store']);
Route::patch('/claims/{claim}', [ProjectClaimController::class, 'update']);
Route::get('/diagnostics/latest', [ProjectDiagnosisController::class, 'show']);
Route::post('/diagnostics', [ProjectDiagnosisController::class, 'store']);
```

Resolve claims through `$request->attributes->get('project')` relationships. Resources
must expose human-readable Arabic labels and raw stable enum values separately.

- [ ] **Step 4: Run API and authorization tests**

Run: `php artisan test tests/Feature/Api/V1/ProjectDiagnosisApiTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/ProjectClaimController.php app/Http/Controllers/Api/V1/ProjectDiagnosisController.php app/Http/Requests/Api/V1/StoreProjectClaimRequest.php app/Http/Resources/Diagnosis routes/api.php tests/Feature/Api/V1/ProjectDiagnosisApiTest.php
git commit -m "feat: expose project diagnosis api"
```

### Task 7: Build the RTL diagnostic matrix on the web

**Files:**
- Create: `tests/Feature/Web/ProjectDiagnosisPageTest.php`
- Create: `app/Http/Controllers/Web/ProjectDiagnosisController.php`
- Create: `resources/views/app/projects/diagnosis.blade.php`
- Modify: `routes/web.php`
- Modify: active project navigation partial located by the existing project view include.

- [ ] **Step 1: Write failing page tests**

Assert the owner sees five explicitly named scores, nine axes, evidence status, missing
information, and one next action. Assert the page contains none of `تشغيل حملة`,
`نشر تلقائي`, `.env`, or a bare percentage without a score label. Assert an empty
project receives an honest insufficient-data state.

- [ ] **Step 2: Run and verify RED**

Run: `php artisan test tests/Feature/Web/ProjectDiagnosisPageTest.php`

Expected: FAIL because `projects.diagnosis.show` is missing.

- [ ] **Step 3: Implement route, controller, and view**

Add:

```php
Route::get('/projects/{project}/diagnosis', [ProjectDiagnosisController::class, 'show'])
    ->name('projects.diagnosis.show');
```

The controller authorizes the project and returns the latest ready snapshot. The view
uses existing design tokens, semantic headings, RTL tables/cards, visible focus states,
and Arabic status labels. Link the old recommendations route to this page only after
the legacy import succeeds; preserve route compatibility with a 302 redirect.

- [ ] **Step 4: Run web, accessibility-contract, and route tests**

Run: `php artisan test tests/Feature/Web/ProjectDiagnosisPageTest.php tests/Feature --filter=Recommendation`

Expected: PASS with no 404 on the legacy recommendations URL.

- [ ] **Step 5: Build assets and commit**

Run: `npm run build`

Expected: Vite build completes without errors.

```bash
git add app/Http/Controllers/Web/ProjectDiagnosisController.php resources/views/app/projects/diagnosis.blade.php routes/web.php tests/Feature/Web/ProjectDiagnosisPageTest.php
git commit -m "feat: add project diagnostic matrix"
```

### Task 8: Phase-one verification and migration rehearsal

**Files:**
- Create: `docs/rebuild/diagnostic-foundation-runbook.md`
- Modify only files required by failures discovered in this task.

- [ ] **Step 1: Run formatting and focused tests**

Run: `vendor/bin/pint --test`

Expected: PASS.

Run: `php artisan test tests/Unit/Diagnosis tests/Feature/Diagnosis tests/Feature/Api/V1/ProjectDiagnosisApiTest.php tests/Feature/Web/ProjectDiagnosisPageTest.php`

Expected: PASS.

- [ ] **Step 2: Run the full Laravel suite**

Run: `php artisan test`

Expected: all tests pass with no warnings or deprecations introduced by this phase.

- [ ] **Step 3: Rehearse migration on a database copy**

Run: `php artisan diagnosis:import-legacy --dry-run`

Expected: reports project, claim, and evidence counts without changing the database.

Run: `php artisan diagnosis:import-legacy`

Expected: every eligible project is imported; a second run reports zero new records.

- [ ] **Step 4: Write the runbook**

Document backup, migrate, dry-run, import, count reconciliation, rollback boundary,
and feature-flag activation commands with the exact observed counts from rehearsal.

- [ ] **Step 5: Commit verification documentation**

```bash
git add docs/rebuild/diagnostic-foundation-runbook.md
git commit -m "docs: add diagnostic migration runbook"
```

## Follow-on Plans Required for Full Rebuild

After this phase is green, write and execute these plans in order:

1. `needs-and-agency-briefs`: project needs, scopes, deliverables, readiness workflow,
   frozen/versioned agency briefs, secure sharing, and review threads.
2. `proposal-analysis-and-web-rebuild`: proposal ingestion, deterministic checks,
   comparison matrix, decision dashboard, navigation, billing cleanup, and studio
   repositioning.
3. `flutter-diagnostic-journey`: new mobile navigation, quick intake, claims/evidence,
   diagnosis matrix, readiness, briefs, proposal summaries, offline snapshots, and
   optimistic concurrency.
4. `legacy-retirement-and-acceptance`: dual-read removal, legacy route redirects,
   end-to-end owner/agency journeys, accessibility, performance, and production rollout.
