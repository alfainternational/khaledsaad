# Unified Agency Report Implementation Plan

**Goal:** Let a project owner create an immutable, versioned, agency-ready report from the latest valid report of each completed tool and share it as a branded PDF.

**Architecture:** `AgencyReportService` selects latest published reports by tool, enforces a three-tool core gate, applies per-category visibility choices, and stores a complete JSON snapshot. Pages and PDFs read only that snapshot, so a delivered version never changes. New project data creates a new version.

## Task 1: Immutable report model and readiness

- Add `agency_reports` migration and model.
- Add project relationship.
- Implement readiness using `marketing-score`, `brand-clarity`, and `audience-map` as the required core.
- Add failing tests for missing-core rejection and latest-per-tool selection.

## Task 2: Unified snapshot

- Include project profile, readiness score, every completed tool, evidence/assumptions, ranked priorities, deterministic 30/60/90 plan, KPIs, scope, ownership, review cadence, agency comparison questions, methodology, source report IDs, and snapshot timestamp.
- Apply visibility choices (`full`, `summary`, `private`) to budget, competitors, and evidence before persistence.
- Add tests proving old versions remain unchanged.

## Task 3: Web and API delivery

- Add owner-only web routes for readiness, generation, show, and PDF.
- Add equivalent API endpoints.
- Add project-page entry and generation controls with recommended defaults.
- Add ownership and response-contract tests.

## Task 4: Branded PDF

- Generate Arabic RTL PDF with the project snapshot only.
- Cache by agency-report ID and template version.
- Add generation/download/authorization tests.

## Task 5: Flutter parity

- Add agency-report models and repository calls.
- Add a project-screen entry and a report screen with generation, summary, priorities, plan, scope, and PDF download.
- Add parsing tests and run Flutter analyze/tests.

## Task 6: Final verification

- Run Pint, Vite build, Laravel full suite, Flutter analyze/tests, and Flutter debug APK build.
- Produce the final implementation/audit report only from the verified state in this directory.
