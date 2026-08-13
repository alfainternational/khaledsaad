# Unified Report Contract — Release Evidence

Prepared: 2026-08-12  
Branch: `codex/unified-report-contract`  
Migrations:

- `2026_08_12_120000_create_unified_report_contract.php`
- `2026_08_12_121000_add_template_payload_to_recommendations.php`

## Scope delivered

- One objective catalog and exact objective-keyed template resolver for all 11 diagnostic tools.
- Complete recommendation contract: deliverable, template, done condition, first five minutes, expected failure, metric, impact, effort, and duration.
- Pure R01–R15 semantic validation with persisted findings and isolated recommendation degradation.
- Automated, signed, and hybrid provenance, human traces, revisions, and immutable schema-v2 publication payload.
- Auditable raw/max scoring items and equation DTO.
- Unified presenter additions for web, PDF, API v1, shared pages, agency aggregation, readiness fixes, and Flutter.
- Gated trial-report reset with dry-run default and mandatory backup.
- Recoverable transition backup containing nested report data, validation/revision traces, feedback, agency share metadata, and copied PDF files before deletion.

## Verified commands

- Final Laravel acceptance suite: 64 passed, 431 assertions.
- The acceptance suite covers migration, objective/template resolution, R01-R15, automated/manual publication, score transparency, tool pipeline, agency aggregation, readiness, web/PDF parity, and the trial reset.
- Extended report-route suite: 34 passed, 281 assertions, covering agency delivery/freshness/sharing, audience-separated owner and agency documents, legacy links, API portfolio/briefs, competitor sections, charts, and PDF authorization/downloads.
- Flutter: `flutter analyze` — no issues.
- Flutter report/agency focused tests: 4 passed.
- Front-end: `npm run build` — successful Vite production build.
- Syntax: 24 new contract/publication/migration files pass `php -l`.
- Formatting: scoped new PHP files pass Laravel Pint.
- Whitespace: scoped `git diff --check` completed without errors.
- Visual PDF QA: two A4 pages inspected after rendering; header overlap fixed, contract card kept together, and `{business_name}` now resolves to the project name from the immutable template snapshot.

## Deployment-only sequence

1. Take a database and report-file backup; preserve the production `APP_KEY`.
2. Deploy the scoped files from this branch.
3. Run `php artisan migrate --force`.
4. Run `php artisan db:seed --class=ReportingContractSeeder --force`.
5. Run `php artisan optimize:clear && php artisan optimize`.
6. Run `php artisan reports:trial-reset` and review counts (dry-run only).
7. Create a writable backup directory, then run the same command with `--execute`, `--backup=<absolute-path>`, and `--production-confirmation=RESET-TRIAL-REPORTS`; retain the JSON manifest and copied PDF folder.
8. Smoke-test automated and signed report generation, API v1 response, web/shared/PDF pages, agency report, readiness card, and Flutter against production.

No production reset or deployment was executed while preparing this change.

## Environmental notes

- The local MySQL schema reports the unified migration as applied.
- Legacy route/API fixtures were updated to activate the business experience like real users; the extended report suite now passes without middleware redirects.
- CodeRabbit CLI was not installed, so the optional external review was unavailable; local semantic, feature, syntax, formatting, build, Flutter, and visual PDF gates were completed.
- The working tree contained extensive unrelated pre-existing changes. No destructive cleanup or history rewrite was performed.
