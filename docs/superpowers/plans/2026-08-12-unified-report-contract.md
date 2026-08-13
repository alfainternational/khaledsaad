# Unified Report Contract Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Make every diagnostic report surface publish only versioned, evidence-backed recommendations whose templates match their objectives.

**Architecture:** Extend the existing report entities with an objective-keyed contract and immutable publication snapshot. Build a pure semantic validator, then route automated, signed, hybrid, aggregate, readiness, web, PDF, API, shared, and Flutter surfaces through one presenter contract. Preserve API v1 compatibility and use forward-only migrations.

**Tech Stack:** Laravel 13, PHP 8.3, SQLite/MySQL migrations, Blade, mPDF, Flutter 3/Dart, PHPUnit.

---

### Task 1: Contract data model

**Files:**
- Create: `database/migrations/2026_08_12_120000_create_unified_report_contract.php`
- Create: `app/Models/Objective.php`
- Create: `app/Models/RecommendationTemplate.php`
- Create: `app/Models/TemplateBinding.php`
- Create: `app/Models/ReportRevision.php`
- Create: `app/Models/ValidationFinding.php`
- Create: `app/Models/HumanTrace.php`
- Create: `app/Models/TemplateGap.php`
- Create: `app/Models/ScoringItem.php`
- Modify: `app/Models/Report.php`
- Modify: `app/Models/Finding.php`
- Modify: `app/Models/Recommendation.php`
- Modify: `app/Models/AgencyReport.php`
- Test: `tests/Feature/UnifiedReportContractMigrationTest.php`

- [x] Write a migration test that asserts all contract tables, columns, indexes, casts, and relationships.
- [x] Run `php artisan test tests/Feature/UnifiedReportContractMigrationTest.php` and confirm it fails for missing tables.
- [x] Add the forward-only migration and models with no destructive legacy data rewrite.
- [x] Run the migration test and confirm it passes on SQLite.

### Task 2: Objective catalog and exact template resolution

**Files:**
- Create: `database/data/reporting/objectives.php`
- Create: `database/data/reporting/templates.php`
- Create: `app/Modules/Reporting/Objectives/ObjectiveCatalog.php`
- Create: `app/Modules/Reporting/Templates/TemplateResolver.php`
- Create: `app/Modules/Reporting/Templates/ResolvedTemplate.php`
- Create: `database/seeders/ReportingContractSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Unit/Modules/Reporting/TemplateResolverTest.php`
- Test: `tests/Feature/ReportingContractSeederTest.php`

- [x] Write failing tests proving resolution uses `objective_id`, rejects a kind-only match, binds known context, and records missing templates/context.
- [x] Run both focused tests and confirm the expected failures.
- [x] Implement the catalog, seeded objectives/templates, closed transforms, and resolver.
- [x] Run both focused tests and confirm they pass.

### Task 3: Recommendation contract builder

**Files:**
- Create: `app/Modules/Reporting/Contracts/RecommendationContractBuilder.php`
- Create: `app/Modules/Reporting/Contracts/RecommendationCandidate.php`
- Modify: `app/Services/Tools/DeterministicInsights.php`
- Modify: `app/Services/Tools/PipelineSchemas.php`
- Modify: `app/Modules/Execution/RecommendationEnricher.php`
- Modify: `app/Modules/Execution/DeterministicExampleFactory.php`
- Test: `tests/Unit/Modules/Reporting/RecommendationContractBuilderTest.php`

- [x] Write failing tests for all five required fields, exact objective/metric linkage, explicit missing-template degradation, and absence of generic fallback steps.
- [x] Run the focused test and confirm failures arise from missing contract behavior.
- [x] Implement the builder and remove the prohibited fallback/deferral paths.
- [x] Run the focused test and existing recommendation enricher tests.

### Task 4: R01-R15 semantic validation

**Files:**
- Create: `app/Modules/Reporting/Validation/SemanticValidator.php`
- Create: `app/Modules/Reporting/Validation/ValidationReport.php`
- Create: `app/Modules/Reporting/Validation/ValidationViolation.php`
- Create: `app/Modules/Reporting/Validation/ArabicJaccard.php`
- Modify: `app/Modules/Shared/Text/ArabicText.php`
- Test: `tests/Unit/Modules/Reporting/SemanticValidatorTest.php`
- Fixture: `tests/Fixtures/reports/diagnosis-58-invalid.json`
- Fixture: `tests/Fixtures/reports/diagnosis-58-valid.json`

- [x] Write one failing test per R01-R15 plus the invalid/valid report regression pair.
- [x] Run the focused suite and inspect each expected rule code.
- [x] Implement pure validation without mutation and persistable violation output.
- [x] Run the focused suite and confirm all rules pass.

### Task 5: Automated, signed, and hybrid publication pipeline

**Files:**
- Create: `app/Modules/Reporting/Publication/ReportPublicationGate.php`
- Create: `app/Modules/Reporting/Publication/ReportContractAssembler.php`
- Create: `app/Modules/Reporting/Publication/Provenance.php`
- Modify: `app/Services/Tools/ToolRunPipeline.php`
- Modify: `app/Services/Tools/ManualReportService.php`
- Modify: `app/Services/Tools/ReportComposer.php`
- Modify: `app/Services/Tools/V2/ReportSemanticGuard.php`
- Test: `tests/Feature/ReportPublicationGateTest.php`
- Test: `tests/Feature/ManualReportTest.php`

- [x] Write failing publication tests for automated repair-once/degrade, signed trace requirement, hybrid diff, and transactional publication.
- [x] Run the focused tests and confirm the gate is absent.
- [x] Implement candidate assembly, one repair callback, isolated degradation, revision/traces, validation persistence, and schema snapshot.
- [x] Run the publication and manual-path tests.

### Task 6: Deterministic score auditability

**Files:**
- Modify: `app/Modules/Diagnosis/DeterministicScorer.php`
- Modify: `app/Modules/Diagnosis/AxisScorer.php`
- Modify: `app/Services/Tools/ReportComposer.php`
- Modify: `app/Support/Presentation/ReportPresenter.php`
- Test: `tests/Feature/ScoreTransparencyTest.php`
- Test: `tests/Unit/Services/Tools/DeterministicScorerTest.php`

- [x] Write failing tests for raw/max score output, persisted scoring items, and ±0.5 equation validation.
- [x] Run the focused tests and confirm the missing raw/max behavior.
- [x] Return and persist raw/max/coefficient rows and expose one score equation DTO.
- [x] Run both focused tests.

### Task 7: Aggregate and specialized reports

**Files:**
- Modify: `app/Modules/Reporting/AgencyReportService.php`
- Modify: `app/Modules/Reporting/AgencyReportDocumentAdapter.php`
- Modify: `app/Modules/Reporting/AgencyReportSharing.php`
- Modify: `app/Modules/Diagnosis/FixList.php`
- Modify: `app/Http/Controllers/App/ReadinessController.php`
- Test: `tests/Feature/AgencyReportContractTest.php`
- Test: `tests/Feature/ReadinessContractTest.php`

- [x] Write failing tests that reject an unvalidated source, preserve every recommendation field in priorities, and turn each readiness fix into a complete action contract.
- [x] Run the focused tests and confirm the gaps.
- [x] Implement aggregate validation and specialized action adaptation without duplicating recommendations.
- [x] Run both focused tests plus existing agency/readiness suites.

### Task 8: One presentation contract for web, PDF, API, shared pages, and Flutter

**Files:**
- Modify: `app/Support/Presentation/ReportPresenter.php`
- Create: `resources/views/components/provenance-badge.blade.php`
- Create: `resources/views/components/recommendation-contract.blade.php`
- Create: `resources/views/components/score-equation.blade.php`
- Modify: `resources/views/app/reports/show.blade.php`
- Modify: `resources/views/reports/pdf.blade.php`
- Modify: `resources/views/reports/shared.blade.php`
- Modify: `resources/views/reports/readiness-card.blade.php`
- Modify: `resources/views/agency-reports/partials/owner-document.blade.php`
- Modify: `resources/css/workspace.css`
- Modify: `mobile/lib/features/reports/models.dart`
- Modify: `mobile/lib/features/reports/report_screen.dart`
- Modify: `mobile/lib/features/agency_reports/models.dart`
- Modify: `mobile/lib/features/agency_reports/agency_report_screen.dart`
- Test: `tests/Feature/UnifiedReportPresentationTest.php`
- Test: `tests/Feature/ReportPdfParityTest.php`
- Test: `mobile/test/features/report_contract_test.dart`

- [x] Write failing parity tests for provenance copy, five fields, score equation, missing-template state, signed margin, and banned anti-machine phrases.
- [x] Run Laravel and Flutter focused tests and confirm existing presenters omit fields.
- [x] Implement shared Blade components, STYLESEED-compatible report styling, API DTO additions, and Flutter models/widgets.
- [x] Run Laravel/Flutter focused tests and inspect rendered HTML/PDF.

### Task 9: Trial-report transition command

**Files:**
- Create: `app/Console/Commands/ResetTrialReports.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/ResetTrialReportsCommandTest.php`

- [x] Write failing tests for dry-run default, mandatory backup path, production confirmation, scoped deletion, and preservation of accounts/projects/answers/evidence/billing.
- [x] Run the focused command test and confirm the command is missing.
- [x] Implement manifest backup, recoverable file handling, and transaction-scoped report cleanup.
- [x] Run the focused test and inspect a generated backup fixture.

### Task 10: Final gates and release evidence

**Files:**
- Modify: `tests/Feature/ReportPdfTest.php`
- Modify: `tests/Feature/ReportPdfParityTest.php`
- Modify: `tests/Feature/UnifiedDiagnosticPresentationTest.php`
- Modify: `docs/product/parity-matrix.yaml`
- Create: `docs/product/release-evidence/unified-report-contract/README.md`

- [x] Run focused PHP suites for contract, publication, manual, aggregate, readiness, presentation, PDF, and reset.
- [x] Run `php artisan test`, `vendor/bin/pint --test`, and `git diff --check`.
- [x] Run `flutter analyze` and `flutter test` from `mobile`.
- [x] Run `npm run build`.
- [x] Render representative automated/signed/hybrid and readiness PDFs; inspect A4 page images for clipping and overlap.
- [x] Record exact commands, counts, known environmental limits, migration name, and deployment-only steps in release evidence.


