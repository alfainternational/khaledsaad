# Unified Diagnostic Intelligence Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the shared diagnostic knowledge flow so consultations, tools, evidence, reports, web, and Flutter use one traceable source of truth with no feature parity gaps.

**Architecture:** Keep `project_answers` as the compatible current-value projection and add an append-only provenance ledger. Build a consultation context once on the server, attach consultation runs to it, include prior cross-tool results, compose one versioned agency-report snapshot, and expose freshness and provenance through the existing web/API contracts.

**Tech Stack:** Laravel 12, Eloquent/MySQL, Blade, PHPUnit, Flutter/Dart, existing report/PDF pipeline.

---

### Task 1: Add append-only project knowledge provenance

**Files:**
- Create: `database/migrations/2026_07_27_100000_create_project_knowledge_sources_table.php`
- Create: `app/Models/ProjectKnowledgeSource.php`
- Create: `app/Services/Projects/ProjectKnowledgeService.php`
- Modify: `app/Models/Project.php`
- Modify: `app/Services/Tools/ProjectAnswerMemory.php`
- Modify: `app/Services/Consultations/ConsultationService.php`
- Modify: `app/Services/Projects/ProjectService.php`
- Test: `tests/Feature/ProjectKnowledgeIntegrationTest.php`

- [ ] Write tests proving tool, consultation, and profile writes preserve source history while updating one canonical value.
- [ ] Run the focused test and confirm it fails because the ledger does not exist.
- [ ] Add the migration, model, backfill, and knowledge service; route every write through it.
- [ ] Run the focused test and existing memory/consultation tests until green.
- [ ] Commit the provenance slice.

### Task 2: Extract and distribute consultation evidence

**Files:**
- Create: `database/migrations/2026_07_27_101000_complete_consultation_evidence_and_run_links.php`
- Create: `app/Services/Consultations/ConsultationContextBuilder.php`
- Modify: `app/Models/ConsultationEvidence.php`
- Modify: `app/Models/ToolRun.php`
- Modify: `app/Services/Tools/AttachmentExtractor.php`
- Modify: `app/Services/Consultations/ConsultationEvidenceService.php`
- Modify: `app/Services/Tools/FullDiagnosisRunner.php`
- Modify: `app/Services/Tools/ProjectSnapshotBuilder.php`
- Test: `tests/Feature/ConsultationEvidenceIntegrationTest.php`

- [ ] Write tests proving an uploaded text/PDF/office file is extracted, hashed, and present in every consultation run snapshot.
- [ ] Run the tests and confirm the missing extraction/link assertions fail.
- [ ] Generalize stored-file extraction, extend evidence metadata, link runs to sessions, and add the context builder.
- [ ] Run evidence, pipeline, privacy, and consultation tests until green.
- [ ] Commit the evidence slice.

### Task 3: Add cross-tool context and unified synthesis

**Files:**
- Create: `app/Services/Reports/CrossToolSynthesis.php`
- Modify: `app/Services/Tools/ProjectSnapshotBuilder.php`
- Modify: `app/Services/Reports/AgencyReportService.php`
- Modify: `app/Jobs/FinishFullDiagnosis.php`
- Modify: `app/Services/Consultations/ConsultationReportGate.php`
- Test: `tests/Feature/UnifiedDiagnosticSynthesisTest.php`

- [ ] Write tests proving prior tool findings enter later snapshots and the unified report contains sourced consultation facts, evidence, inferences, conflict resolutions, and cross-tool results.
- [ ] Run the tests and confirm they fail on the missing snapshot sections.
- [ ] Implement deterministic sourced aggregation and pass the explicit consultation session into report generation.
- [ ] Strengthen the publication gate so unsupported synthesis cannot publish.
- [ ] Run report, PDF, full-diagnosis, and semantic-guard tests until green.
- [ ] Commit the synthesis slice.

### Task 4: Detect stale reports without rewriting history

**Files:**
- Create: `app/Services/Reports/ReportFreshnessService.php`
- Modify: `app/Services/Reports/AgencyReportService.php`
- Modify: `app/Http/Controllers/Api/V1/AgencyReportController.php`
- Modify: `app/Http/Controllers/App/AgencyReportController.php`
- Modify: `resources/views/app/agency-reports/index.blade.php`
- Modify: `resources/views/app/agency-reports/show.blade.php`
- Test: `tests/Feature/AgencyReportFreshnessTest.php`

- [ ] Write tests for fresh reports, answer/evidence/report changes, reason labels, and immutable old snapshots.
- [ ] Run the tests and confirm freshness fields and UI are absent.
- [ ] Implement one server-side freshness calculation and expose it through API and Blade.
- [ ] Run controller, sharing, PDF, and report tests until green.
- [ ] Commit the freshness slice.

### Task 5: Complete report and PDF presentation

**Files:**
- Modify: `resources/views/app/agency-reports/show.blade.php`
- Modify: `resources/views/reports/agency-pdf.blade.php`
- Modify: `app/Services/Reports/AgencyReportPdfService.php`
- Test: `tests/Feature/UnifiedDiagnosticPresentationTest.php`

- [ ] Write rendering tests for sources, consultation evidence, assumptions, conflicts, cross-tool synthesis, privacy modes, and freshness.
- [ ] Run the tests and confirm the new sections are missing.
- [ ] Add accessible RTL sections with safe truncation and privacy filtering.
- [ ] Run view/PDF tests and inspect a rendered sample.
- [ ] Commit the web/PDF slice.

### Task 6: Match the Flutter contract and experience

**Files:**
- Modify: `mobile/lib/features/consultations/models.dart`
- Modify: `mobile/lib/features/consultations/consultation_screen.dart`
- Modify: `mobile/lib/features/agency_reports/models.dart`
- Modify: `mobile/lib/features/agency_reports/agency_report_screen.dart`
- Modify: `mobile/lib/features/agency_reports/agency_reports_screen.dart`
- Test: `mobile/test/consultation_unified_context_test.dart`
- Test: `mobile/test/agency_report_freshness_test.dart`

- [ ] Write widget/model tests for extraction state, sources, unified sections, freshness, and regeneration.
- [ ] Run the focused Flutter tests and confirm they fail on the missing contract/UI.
- [ ] Extend models and screens using only API-provided values.
- [ ] Run focused tests, all Flutter tests, and `flutter analyze` until green.
- [ ] Commit the Flutter parity slice.

### Task 7: Backfill, audit, and release

**Files:**
- Modify: `docs/product/parity-matrix.yaml`
- Modify: `tests/Unit/ProductQuality/ParityMatrixTest.php`
- Modify: `public/downloads/release.json`
- Build: `output/Khaled-Saad-Growth-<version>.apk`
- Build: `output/Khaled-Saad-Growth-<version>.aab`

- [ ] Run migrations and verify existing projects/reports remain readable and provenance is backfilled.
- [ ] Run Laravel tests, Pint, Vite build, product audit, Flutter analyze/tests, APK build, and AAB build.
- [ ] Review the complete diff and run the code-review workflow; fix important findings and reverify.
- [ ] Merge the branch into `main`, push the exact commit, back up production, deploy, migrate, seed, clear caches, and restart workers.
- [ ] Publish the APK and release manifest, verify the production API/web/admin/download paths, hash, authenticated consultation, unified report, and rollback backup.

