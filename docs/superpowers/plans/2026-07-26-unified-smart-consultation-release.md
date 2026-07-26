# Unified Smart Consultation Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver one complete production release in which web and Flutter guide users through a unified adaptive consultation, produce an evidence-backed unified report, and preserve all current users, projects, tool runs, reports, and agency reports.

**Architecture:** Laravel remains the only decision engine and source of truth. A versioned consultation catalog imports the existing eleven tool definitions into a canonical question bank, a deterministic engine selects the next question and records evidence/confidence/conflicts, and the current full-diagnosis plus agency-report pipeline produces the final report. Blade and Flutter consume the same versioned API and never reproduce decision rules.

**Tech Stack:** PHP 8.3, Laravel 13, MariaDB/MySQL, PHPUnit 12, Blade, Vite 7, Flutter 3.44, Dart 3.12, Firebase, database queues.

---

## File structure

- `config/consultation.php`: default blueprint, module applicability, question weights, stop limits, source confidence, and safe event allowlist.
- `database/migrations/2026_07_26_120000_create_consultation_tables.php`: additive consultation schema and nullable report linkage.
- `app/Models/Consultation*.php`, `QuestionDefinition.php`, `QuestionVersion.php`, `DiagnosticModule.php`: persisted versioned domain.
- `app/Services/Consultations/Catalog/*`: deterministic import and publishing of the existing tool catalog.
- `app/Services/Consultations/Engine/*`: scope, priority, stop, conflict, answer, and session orchestration.
- `app/Services/Consultations/ConsultationPresenter.php`: one response model for Blade and API.
- `app/Http/Controllers/App/ConsultationController.php`: web journey.
- `app/Http/Controllers/Api/V1/ConsultationController.php`: Flutter contract.
- `app/Http/Controllers/Admin/AdminConsultationController.php`: catalog governance and simulator.
- `resources/views/app/consultations/*`: web start, question, review, and status.
- `resources/views/admin/consultations/*`: version list, question audit, and simulation.
- `mobile/lib/features/consultations/*`: Flutter model, repository, and journey screen.
- `tests/Unit/Services/Consultations/*`: deterministic engine coverage.
- `tests/Feature/ConsultationJourneyTest.php`: web journey and ownership.
- `tests/Feature/ConsultationApiTest.php`: API contract and cross-device resume.
- `tests/Feature/AdminConsultationTest.php`: version governance.
- `tests/Feature/ConsultationReportTest.php`: full-diagnosis/report linkage.
- `mobile/test/features/consultation_models_test.dart`: API model parsing.
- `mobile/test/features/consultation_journey_test.dart`: Flutter states and submission.

### Task 1: Add the additive consultation schema and models

**Files:**
- Create: `database/migrations/2026_07_26_120000_create_consultation_tables.php`
- Create: `app/Models/ConsultationBlueprint.php`
- Create: `app/Models/ConsultationBlueprintVersion.php`
- Create: `app/Models/DiagnosticModule.php`
- Create: `app/Models/QuestionDefinition.php`
- Create: `app/Models/QuestionVersion.php`
- Create: `app/Models/ConsultationSession.php`
- Create: `app/Models/ConsultationModuleState.php`
- Create: `app/Models/ConsultationAnswer.php`
- Create: `app/Models/ConsultationEvidence.php`
- Create: `app/Models/ConsultationConflict.php`
- Create: `app/Models/ConsultationInference.php`
- Create: `app/Models/ConsultationEvent.php`
- Modify: `app/Models/Project.php`
- Modify: `app/Models/AgencyReport.php`
- Test: `tests/Feature/ConsultationSchemaTest.php`

- [ ] **Step 1: Write a failing schema and relationship test**

```php
#[Test]
public function consultation_schema_is_additive_and_links_to_projects_and_reports(): void
{
    $project = Project::factory()->create();
    $session = ConsultationSession::create([
        'uuid' => (string) Str::uuid(),
        'project_id' => $project->id,
        'blueprint_version_id' => ConsultationBlueprintVersion::factory()->create()->id,
        'status' => ConsultationSession::STATUS_ACTIVE,
        'depth' => 'standard',
    ]);

    $this->assertSame($project->id, $session->project->id);
    $this->assertTrue($project->consultationSessions->contains($session));
}
```

- [ ] **Step 2: Run the test and confirm missing tables/classes**

Run: `php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ConsultationSchemaTest.php`

Expected: FAIL because consultation models/tables do not exist.

- [ ] **Step 3: Add tables with foreign keys, indexes, JSON casts, timestamps, and nullable `agency_reports.consultation_session_id`**

Required constraints include unique blueprint/version pairs, unique question/version pairs, unique session/question answers, unique session/module states, and indexes on session status/last activity. All foreign-key deletes must preserve historical reports or cascade only session-owned transient records.

- [ ] **Step 4: Implement explicit fillable/casts/relationships and UUID route keys**

- [ ] **Step 5: Run the schema test and existing migration tests**

Run: `php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ConsultationSchemaTest.php tests\Feature\AgencyReportTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add database/migrations/2026_07_26_120000_create_consultation_tables.php app/Models tests/Feature/ConsultationSchemaTest.php
git commit -m "feat: add versioned consultation domain"
```

### Task 2: Build and publish the canonical catalog from current tools

**Files:**
- Create: `config/consultation.php`
- Create: `app/Services/Consultations/Catalog/ConsultationCatalogBuilder.php`
- Create: `app/Services/Consultations/Catalog/ConsultationCatalogValidator.php`
- Create: `database/seeders/ConsultationCatalogSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/ConsultationCatalogTest.php`

- [ ] **Step 1: Write failing tests for one published blueprint, nineteen modules, gateway questions, and imports of every published tool field**

```php
$this->seed([ToolCatalogSeeder::class, ConsultationCatalogSeeder::class]);
$version = ConsultationBlueprint::where('key', 'smart-marketing-consultation')
    ->firstOrFail()->currentVersion;

$this->assertSame('published', $version->status);
$this->assertCount(19, $version->modules);
$this->assertSame(
    ToolField::whereHas('toolVersion.tool', fn ($q) => $q->where('status', 'published'))->count(),
    QuestionDefinition::whereNotNull('legacy_tool_field_id')->count(),
);
```

- [ ] **Step 2: Run and confirm failure**

Run: `php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ConsultationCatalogTest.php`

- [ ] **Step 3: Define safe configuration**

Configuration must include depth limits `quick=18`, `standard=35`, `deep=60`; the nineteen module keys; deterministic applicability predicates; source-confidence defaults; sensitivity burden; and the event allowlist. Do not store executable PHP or SQL in database rules.

- [ ] **Step 4: Implement idempotent catalog import**

Stable imported IDs use `TOOLKEY.FIELDKEY`; gateway IDs use `START-01` through `START-12`. Published versions are immutable; reseeding creates or updates only a draft until explicitly published.

- [ ] **Step 5: Validate every question**

Reject missing internal variables, diagnostic impact, answer type, user text, module binding, unknown handling, or a rule operator outside the allowlist.

- [ ] **Step 6: Run catalog tests and seed twice to prove idempotency**

Run: `php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ConsultationCatalogTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

### Task 3: Implement deterministic module scope and next-question selection

**Files:**
- Create: `app/Services/Consultations/Engine/ConsultationContext.php`
- Create: `app/Services/Consultations/Engine/ModuleScopeResolver.php`
- Create: `app/Services/Consultations/Engine/QuestionPriority.php`
- Create: `app/Services/Consultations/Engine/StopDecider.php`
- Create: `app/Services/Consultations/Engine/NextQuestionSelector.php`
- Test: `tests/Unit/Services/Consultations/ModuleScopeResolverTest.php`
- Test: `tests/Unit/Services/Consultations/NextQuestionSelectorTest.php`

- [ ] **Step 1: Write failing scope tests for idea, operating B2C, B2B, website-less, and authorized-security cases**

- [ ] **Step 2: Write failing ordering and stop tests**

```php
$selected = $selector->next($session);
$this->assertSame('START-05', $selected->definition->key);
$this->assertSame('highest_information_value', $selected->reason);
```

Cover applicability, impact, uncertainty, discrimination, burden, sensitivity, duplicate penalty, unanswered critical questions, depth limits, and decision stability.

- [ ] **Step 3: Run and confirm failures**

Run: `php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\Services\Consultations`

- [ ] **Step 4: Implement pure deterministic services**

The priority formula is `(applicability * impact * uncertainty * discrimination) / max(1, burden + sensitivity + duplicatePenalty)`. Ties resolve by stable question ID. No AI call is allowed in these services.

- [ ] **Step 5: Run unit tests**

Expected: PASS with no database/network dependency except catalog fixtures.

- [ ] **Step 6: Commit**

### Task 4: Implement session lifecycle, answers, evidence, unknown handling, and conflicts

**Files:**
- Create: `app/Services/Consultations/ConsultationService.php`
- Create: `app/Services/Consultations/Engine/AnswerRecorder.php`
- Create: `app/Services/Consultations/Engine/EvidenceClassifier.php`
- Create: `app/Services/Consultations/Engine/ConflictDetector.php`
- Create: `app/Services/Consultations/Engine/InferenceLedger.php`
- Create: `app/Services/Consultations/ConsultationPresenter.php`
- Test: `tests/Feature/ConsultationEngineTest.php`

- [ ] **Step 1: Write failing tests for start/resume, answer revision, cross-tool memory, unknown, source confidence, conflict detection, and completion review**

- [ ] **Step 2: Run and confirm failures**

Run: `php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ConsultationEngineTest.php`

- [ ] **Step 3: Implement transactional start and answer operations**

Lock the session row, reread current state, validate against the exact question version, record an immutable event, update `ProjectAnswerMemory`, invalidate affected inferences, refresh module states, and select the next question.

- [ ] **Step 4: Implement explicit unknown behavior**

`unknown` never scores as zero. The engine tries a simpler follow-up, then evidence request, then marks a measurement gap or defers the module.

- [ ] **Step 5: Implement initial conflict rules**

Cover tracking maturity versus claimed CAC, revenue versus profit confusion, incompatible business stage/actual sales, duplicate metrics for the same period, and file-extracted versus user-confirmed values. Unresolved conflicts cannot become facts.

- [ ] **Step 6: Run engine tests**

Expected: PASS.

- [ ] **Step 7: Commit**

### Task 5: Connect completion to full diagnosis and the unified report

**Files:**
- Modify: `app/Services/Tools/FullDiagnosisRunner.php`
- Modify: `app/Jobs/FinishFullDiagnosis.php`
- Modify: `app/Services/Reports/AgencyReportService.php`
- Modify: `app/Models/AgencyReport.php`
- Modify: `app/Services/Tools/PipelineSchemas.php`
- Modify: `app/Services/Tools/ReportComposer.php`
- Create: `app/Services/Consultations/ConsultationReportGate.php`
- Test: `tests/Feature/ConsultationReportTest.php`

- [ ] **Step 1: Write failing tests proving confirmation queues applicable tools only and the final agency report links to the session**

- [ ] **Step 2: Write failing recommendation-contract tests**

Require evidence, root cause, commercial impact, action steps, owner role, resources, timeframe, dependencies, KPI definition/source, baseline/target or missing reason, success/stop conditions, risk, priority, and confidence.

- [ ] **Step 3: Run and confirm failures**

- [ ] **Step 4: Pass `consultationSessionId` through the batch callback and report generation**

Internal tool reports remain hidden implementation detail of the consultation; the user receives one linked unified report.

- [ ] **Step 5: Add the report gate**

Block publication when a claim lacks provenance, a number lacks period/unit/source, score and confidence are conflated, or a recommendation violates the contract. Deterministic fallback may repair missing prose but may not invent numbers.

- [ ] **Step 6: Run report tests plus existing report/PDF tests**

Run: `php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ConsultationReportTest.php tests\Feature\FullDiagnosisTest.php tests\Feature\AgencyReportTest.php tests\Feature\ReportPdfTest.php`

- [ ] **Step 7: Commit**

### Task 6: Expose versioned API contracts

**Files:**
- Create: `app/Http/Controllers/Api/V1/ConsultationController.php`
- Modify: `routes/api.php`
- Modify: `docs/product/parity-matrix.yaml`
- Test: `tests/Feature/ConsultationApiTest.php`

- [ ] **Step 1: Write failing contract and ownership tests**

Required endpoints:

```text
POST /api/v1/projects/{project}/consultations
GET  /api/v1/consultations/{session}
PUT  /api/v1/consultations/{session}/answers/{question}
POST /api/v1/consultations/{session}/review
POST /api/v1/consultations/{session}/confirm
GET  /api/v1/consultations/{session}/status
DELETE /api/v1/consultations/{session}
```

- [ ] **Step 2: Run and confirm 404 failures**

- [ ] **Step 3: Implement thin controllers over `ConsultationService`**

Use `{data, meta?, message?}`, return ownership failures as 404, validation as 422, conflict requiring resolution as 409, and preserve idempotency for answer and confirm operations.

- [ ] **Step 4: Add route and parity evidence**

- [ ] **Step 5: Run API, route coverage, and ownership tests**

- [ ] **Step 6: Commit**

### Task 7: Build the complete responsive web journey

**Files:**
- Create: `app/Http/Controllers/App/ConsultationController.php`
- Modify: `routes/web.php`
- Create: `resources/views/app/consultations/start.blade.php`
- Create: `resources/views/app/consultations/question.blade.php`
- Create: `resources/views/app/consultations/review.blade.php`
- Create: `resources/views/app/consultations/status.blade.php`
- Modify: `resources/views/app/projects/show.blade.php`
- Modify: `resources/views/home.blade.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/ConsultationJourneyTest.php`

- [ ] **Step 1: Write failing web journey tests from project CTA through review and confirmation**

- [ ] **Step 2: Run and confirm route/view failures**

- [ ] **Step 3: Implement server-rendered screens with progressive enhancement**

The question screen must show one question, why it is asked, example/help, unknown/other/skip where allowed, semantic progress, known-answer summary, conflict notice, and auto-save-safe submission. The review screen groups facts, estimates, assumptions, conflicts, and missing data.

- [ ] **Step 4: Implement all loading/error/empty/expired/entitlement states without JavaScript dependency for the core path**

- [ ] **Step 5: Run feature tests, Vite build, and responsive smoke checks**

- [ ] **Step 6: Commit**

### Task 8: Build administration, version governance, and simulation

**Files:**
- Create: `app/Http/Controllers/Admin/AdminConsultationController.php`
- Create: `resources/views/admin/consultations/index.blade.php`
- Create: `resources/views/admin/consultations/show.blade.php`
- Create: `resources/views/admin/consultations/simulate.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/panel-nav.blade.php`
- Test: `tests/Feature/AdminConsultationTest.php`

- [ ] **Step 1: Write failing admin authorization, immutable-publish, validation, and simulator tests**

- [ ] **Step 2: Implement read-first governance UI**

Show versions, modules, questions, reasons, rules, metrics, and simulator output. Publishing clones a draft to a locked version; no historical version is edited.

- [ ] **Step 3: Implement safe mutations with CSRF, admin middleware, validation, confirmation, and audit events**

- [ ] **Step 4: Run admin tests and non-admin forgery tests**

- [ ] **Step 5: Commit**

### Task 9: Add Flutter contracts and repository

**Files:**
- Create: `mobile/lib/features/consultations/models.dart`
- Create: `mobile/lib/features/consultations/consultation_repository.dart`
- Test: `mobile/test/features/consultation_models_test.dart`

- [ ] **Step 1: Write failing model tests using full API fixtures**

Cover question types, options, unknown/skip flags, semantic progress, known facts, conflicts, review groups, status, and linked report.

- [ ] **Step 2: Run and confirm missing types**

Run: `Push-Location mobile; flutter test --no-pub test/features/consultation_models_test.dart; Pop-Location`

- [ ] **Step 3: Implement strict parsing and repository methods over `ApiClient`**

- [ ] **Step 4: Run model tests and Flutter analysis**

- [ ] **Step 5: Commit**

### Task 10: Build the complete Flutter journey and cross-device resume

**Files:**
- Create: `mobile/lib/features/consultations/consultation_screen.dart`
- Create: `mobile/lib/features/consultations/widgets.dart`
- Modify: `mobile/lib/features/projects/project_screen.dart`
- Modify: `mobile/lib/features/projects/dashboard_screen.dart`
- Modify: `mobile/lib/main.dart`
- Test: `mobile/test/features/consultation_journey_test.dart`

- [ ] **Step 1: Write failing widget tests for start, each supported answer type, unknown, retry, conflict, review, confirm, status, and report navigation**

- [ ] **Step 2: Run and confirm failures**

- [ ] **Step 3: Implement one stateful journey backed entirely by API state**

Support RTL, 200% text, semantic labels, 44×44 targets, keyboard dismissal, offline/error retry, session expiry, and route restoration. Never calculate question order or confidence in Dart.

- [ ] **Step 4: Add the primary CTA to project and dashboard screens**

- [ ] **Step 5: Run Flutter tests and analysis**

- [ ] **Step 6: Commit**

### Task 11: Add privacy operations, analytics, and defensive controls

**Files:**
- Create: `app/Services/Consultations/ConsultationEventRecorder.php`
- Create: `app/Services/Consultations/ConsultationPrivacyService.php`
- Modify: consultation controllers and upload handling.
- Modify: `app/Console/Commands/AuditProductQuality.php`
- Test: `tests/Feature/ConsultationPrivacySecurityTest.php`

- [ ] **Step 1: Write failing tests for data export/delete, consent, event redaction, file ownership, rule injection, prompt injection, rate limits, and unauthorized security scope**

- [ ] **Step 2: Implement allowlisted event metadata only**

Never store answer values, report content, email, phone, file text, payment data, or external secrets in analytics events.

- [ ] **Step 3: Implement export/delete and consent records without deleting historical reports that the user elects to retain**

- [ ] **Step 4: Extend `product:audit` with consultation catalog integrity and sensitive-event scanning**

- [ ] **Step 5: Run security/privacy tests and product audit**

- [ ] **Step 6: Commit**

### Task 12: Prove migration, compatibility, performance, and single-gate release readiness

**Files:**
- Create: `tests/Feature/ConsultationMigrationTest.php`
- Create: `tests/Feature/ConsultationPerformanceTest.php`
- Create: `deploy/verify-consultation-release.ps1`
- Modify: `docs/product/parity-matrix.yaml`
- Create: `docs/product/release-evidence/consultation-release/README.md`

- [ ] **Step 1: Write migration tests with legacy users, projects, answers, runs, reports, shares, tasks, and agency reports**

- [ ] **Step 2: Prove zero semantic data loss and historical rendering before/after migration**

- [ ] **Step 3: Add query-count and response-time guards for next-question selection, session resume, and report loading**

- [ ] **Step 4: Add a release verification script**

The script runs migrations on a copied database, Laravel tests, Pint, Vite, product audit, Flutter analysis/tests, APK/AAB builds, route checks, checksum generation, and fails on any incomplete parity record.

- [ ] **Step 5: Run the full local gate**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never
vendor\bin\pint --test
npm run build
php artisan product:audit
Push-Location mobile
flutter analyze --no-pub
flutter test --no-pub
flutter build apk --release --dart-define=API_BASE_URL=https://khaledsaad.net/api
flutter build appbundle --release --dart-define=API_BASE_URL=https://khaledsaad.net/api
Pop-Location
```

- [ ] **Step 6: Verify production-safe cutover and rollback assets**

Keep the public feature flag closed until all evidence is present. Deploy additive schema and source, smoke test internally on production, then atomically open the unified route. Roll back the application pointer on any critical failure; do not restore the database unless an incompatible migration has proved it necessary.

- [ ] **Step 7: Commit release evidence and final parity state**

## Completion rule

This plan is complete only when every checkbox is satisfied, every required web/API/Flutter capability has direct automated evidence, all legacy data remains readable, the signed production Android artifacts build successfully, and the single production cutover can be performed and rolled back safely. Internal task ordering does not authorize partial public delivery.
