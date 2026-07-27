# Platform Stabilization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore a fully green, internally consistent Laravel and Flutter baseline before hybrid insights or the unified agency report are implemented.

**Architecture:** Keep adaptive field rules in tool definitions as the source of truth, update fixtures to follow the visible required fields, and centralize score/report comparisons around the marketing-readiness tool. Complete mobile parity by consuming the existing report API capabilities rather than duplicating business rules.

**Tech Stack:** PHP 8.3, Laravel 13, PHPUnit 12, Blade, Flutter 3.44, Dart 3.12, Vite, Laravel Pint.

---

### Task 1: Align adaptive-question fixtures with the published tool schema

**Files:**
- Modify: `tests/Feature/ToolRunPipelineTest.php`
- Modify: `tests/Feature/ManualReportTest.php`
- Modify: `tests/Feature/GrowthEngineTest.php`
- Modify: `tests/Feature/CreditLifecycleTest.php`
- Modify: `tests/Feature/CompetitorAiContextTest.php`
- Modify: `tests/Feature/ApiParityTest.php`
- Modify: `tests/Feature/AdaptiveQuestionsTest.php`

- [ ] **Step 1: Prove the current failure**

Run:

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ToolRunPipelineTest.php tests\Feature\AdaptiveQuestionsTest.php
```

Expected: failures for `sales_cycle`, early-stage presence questions, and the stale `project.business_model` assertion.

- [ ] **Step 2: Update fixtures with the required conditional answer**

For service businesses, step 4 must include:

```php
'sales_cycle' => 'medium',
```

For SaaS, step 4 must include:

```php
'trial_conversion' => 'medium',
```

For idea-stage projects, fill the visible early-stage questions instead of operational ones.

- [ ] **Step 3: Align sector inference expectations**

Assert:

```php
$this->assertSame('ecommerce', $context['project.sector']);
$this->assertArrayNotHasKey('project.business_model', $context);
```

This preserves the product rule that inferred sector is a hint, while business model remains user-declared.

- [ ] **Step 4: Run the affected tests**

Run:

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ToolRunPipelineTest.php tests\Feature\ManualReportTest.php tests\Feature\GrowthEngineTest.php tests\Feature\CreditLifecycleTest.php tests\Feature\CompetitorAiContextTest.php tests\Feature\ApiParityTest.php tests\Feature\AdaptiveQuestionsTest.php
```

Expected: all selected tests pass.

### Task 2: Make the project score semantically correct

**Files:**
- Modify: `tests/Feature/ToolRunPipelineTest.php`
- Modify: `app/Services/Tools/ToolRunPipeline.php`

- [ ] **Step 1: Write a failing regression test**

Create a marketing-score report, record its project score, then run a different tool and assert the project score remains the marketing-score value:

```php
$this->assertSame(72, $project->fresh()->latest_score);
```

- [ ] **Step 2: Run the regression test and confirm RED**

Expected: the second tool overwrites `latest_score`.

- [ ] **Step 3: Update only for the readiness tool**

In `ToolRunPipeline::baseline`, save `latest_score` only when:

```php
$run->toolVersion->tool->key === 'marketing-score'
```

- [ ] **Step 4: Run the regression and pipeline tests**

Expected: pass with no behavior change to per-tool report scores.

### Task 3: Compare like-for-like reports in project overview

**Files:**
- Create: `tests/Feature/ProjectReportComparisonTest.php`
- Modify: `app/Support/Presentation/ProjectPresenter.php`

- [ ] **Step 1: Write a failing test**

Create reports in this order: marketing score 40, another-tool score 90, marketing score 55. Assert project comparison says `+15`, not a comparison with 90.

- [ ] **Step 2: Run and confirm RED**

Expected: current presenter compares the two latest project reports regardless of tool.

- [ ] **Step 3: Select the latest two marketing-score reports**

Build `latest_report` and `comparison` from reports whose run tool key is `marketing-score`; keep the full reports list unchanged.

- [ ] **Step 4: Run project presenter, API parity, and web journey tests**

Expected: all pass.

### Task 4: Complete Flutter report parity

**Files:**
- Modify: `app/Http/Controllers/Api/V1/ReportController.php`
- Modify: `mobile/lib/features/reports/models.dart`
- Modify: `mobile/lib/features/reports/report_screen.dart`
- Modify: `mobile/lib/core/api/platform_repository.dart`
- Modify: `mobile/test/features/parity_models_test.dart`
- Modify: `tests/Feature/ApiParityTest.php`

- [ ] **Step 1: Write failing PHP and Dart assertions**

Require the report response/model to expose:

```text
is_manually_reviewed, reviewed_at, comparison, watcher, my_verdict, suggestion
```

Also assert the screen source no longer presents `أُنتج بالنموذج غير مسجل`.

- [ ] **Step 2: Run PHP and Flutter tests and confirm RED**

- [ ] **Step 3: Extend the API envelope**

Return comparison and growth metadata from the same services used by the web report page.

- [ ] **Step 4: Extend Flutter models and repository**

Parse the new fields and add repository methods for report watch, unwatch, and feedback using the existing API routes.

- [ ] **Step 5: Update the Flutter screen**

Show manual-review verification, comparison, report-live controls, feedback, and the next suggested tool. Display only tool version and evidence counts in provenance.

- [ ] **Step 6: Run parity and Flutter tests**

Expected: PHP API tests, `flutter analyze`, and `flutter test` pass.

### Task 5: Make manual review available from Flutter

**Files:**
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Api/V1/RunController.php`
- Modify: `mobile/lib/core/api/platform_repository.dart`
- Modify: `mobile/lib/features/tools/run_wizard_screen.dart`
- Modify: `tests/Feature/ManualReportTest.php`
- Modify: `mobile/test/features/parity_models_test.dart`

- [ ] **Step 1: Write a failing API ownership test**

The owner can request manual review; a stranger receives 404; no automatic pipeline job is queued.

- [ ] **Step 2: Run and confirm RED**

- [ ] **Step 3: Add the API action using `ManualReportService`**

- [ ] **Step 4: Add the Flutter choice at review time**

Offer automatic analysis or full manual review, with clear wording that the latter waits for human processing.

- [ ] **Step 5: Run manual-review and Flutter tests**

Expected: pass.

### Task 6: Keep visual sample artifacts inside the repository

**Files:**
- Modify: `tests/Feature/TmpPdfSampleTest.php`

- [ ] **Step 1: Replace the external hard-coded directory**

Use:

```php
$dir = storage_path('app/testing-samples');
File::ensureDirectoryExists($dir);
```

- [ ] **Step 2: Assert generated files exist**

Replace the unconditional assertion with checks for `sample-report.pdf` and `report-web.html`.

- [ ] **Step 3: Run the sample test**

Expected: pass and write only under `storage/app/testing-samples`.

### Task 7: Clear formatting and build gates

**Files:**
- Modify: files reported by Laravel Pint only.

- [ ] **Step 1: Run Pint in fix mode**

```powershell
vendor\bin\pint
```

- [ ] **Step 2: Verify Pint**

```powershell
vendor\bin\pint --test
```

Expected: exit code 0.

- [ ] **Step 3: Verify web build**

```powershell
npm run build
```

Expected: exit code 0.

### Task 8: Full stability gate

**Files:**
- Verify only.

- [ ] **Step 1: Run all Laravel tests**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never
```

Expected: zero failures and zero errors.

- [ ] **Step 2: Run Flutter verification**

```powershell
flutter analyze
flutter test
```

Expected: no analysis issues and all tests pass.

- [ ] **Step 3: Re-run all build/style gates**

```powershell
vendor\bin\pint --test
npm run build
```

Expected: all commands exit 0.

- [ ] **Step 4: Confirm the next phase gate**

Only after every command above succeeds may the hybrid-insight implementation begin. The unified agency report remains blocked until both stability and hybrid-insight verification succeed.
