# Adaptive Interface Layout System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the approved 12/8/4 adaptive layout system to every public, customer, admin, Flutter, and PDF interface without changing the existing colors, shadows, data contracts, or permissions.

**Architecture:** Add semantic layout primitives to the existing CSS and Flutter shared-widget layers, then classify each screen by page family instead of letting generic `auto-fit` grids choose its structure. Keep Blade controllers and API models unchanged; layout selection stays in views and shared presentation helpers, while PDF receives a separate print contract.

**Tech Stack:** Laravel 13, Blade, Tailwind/Vite CSS, PHPUnit, Flutter 3.44, Material 3, Flutter widget tests.

---

### Task 1: Lock the web layout contract with a failing test

**Files:**
- Create: `tests/Feature/AdaptiveInterfaceLayoutTest.php`
- Modify: `resources/css/workspace.css`
- Test: `tests/Feature/AdaptiveInterfaceLayoutTest.php`

- [ ] **Step 1: Write the failing CSS-contract test**

```php
<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdaptiveInterfaceLayoutTest extends TestCase
{
    #[Test]
    public function workspace_css_defines_the_approved_semantic_layout_contract(): void
    {
        $css = file_get_contents(resource_path('css/workspace.css'));

        foreach ([
            '--layout-reading-max: 46rem',
            '--layout-form-max: 56rem',
            '--layout-operational-max: 87.5rem',
            '--layout-page-gap: 2rem',
            '--layout-section-gap: 1.5rem',
            '.layout-grid',
            '.layout-span-12',
            '.layout-span-9',
            '.layout-span-8',
            '.layout-span-6',
            '.layout-span-4',
            '.layout-span-3',
            '.layout-metrics',
            '.layout-main-aside',
            '.layout-report',
            '.layout-form-aside',
            '.layout-page--reading',
            '.layout-page--form',
            '.layout-page--operational',
            '@media (max-width: 64rem)',
            '@media (max-width: 48rem)',
        ] as $contract) {
            $this->assertStringContainsString($contract, $css, $contract);
        }
    }
}
```

- [ ] **Step 2: Run the test and verify the contract is absent**

Run: `php artisan test tests/Feature/AdaptiveInterfaceLayoutTest.php`

Expected: FAIL because `workspace.css` does not yet contain `--layout-reading-max`.

- [ ] **Step 3: Add the semantic grid and width primitives**

Append a focused section to `resources/css/workspace.css` containing this contract:

```css
:root {
    --layout-reading-max: 46rem;
    --layout-form-max: 56rem;
    --layout-operational-max: 87.5rem;
    --layout-page-gap: 2rem;
    --layout-section-gap: 1.5rem;
    --layout-item-gap: 1rem;
}

.layout-page { inline-size: 100%; min-inline-size: 0; }
.layout-page--reading { max-inline-size: var(--layout-reading-max); }
.layout-page--form { max-inline-size: var(--layout-form-max); }
.layout-page--operational { max-inline-size: var(--layout-operational-max); }

.layout-grid {
    display: grid;
    grid-template-columns: repeat(12, minmax(0, 1fr));
    gap: var(--layout-section-gap);
    align-items: start;
}

.layout-span-12 { grid-column: span 12; }
.layout-span-9 { grid-column: span 9; }
.layout-span-8 { grid-column: span 8; }
.layout-span-6 { grid-column: span 6; }
.layout-span-4 { grid-column: span 4; }
.layout-span-3 { grid-column: span 3; }
.layout-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--layout-item-gap); }
.layout-main-aside,
.layout-form-aside { display: grid; grid-template-columns: minmax(0, 2fr) minmax(16rem, 1fr); gap: var(--layout-section-gap); align-items: start; }
.layout-report { display: grid; grid-template-columns: minmax(0, 3fr) minmax(14rem, 1fr); gap: var(--layout-section-gap); align-items: start; }

@media (max-width: 64rem) {
    .layout-grid { grid-template-columns: repeat(8, minmax(0, 1fr)); }
    .layout-span-9, .layout-span-8 { grid-column: span 8; }
    .layout-span-6, .layout-span-4 { grid-column: span 4; }
    .layout-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .layout-main-aside, .layout-form-aside, .layout-report { grid-template-columns: minmax(0, 5fr) minmax(14rem, 3fr); }
}

@media (max-width: 48rem) {
    .layout-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .layout-span-12, .layout-span-9, .layout-span-8, .layout-span-6, .layout-span-4, .layout-span-3 { grid-column: 1 / -1; }
    .layout-main-aside, .layout-form-aside, .layout-report { grid-template-columns: 1fr; }
}
```

- [ ] **Step 4: Run the focused test and Vite build**

Run: `php artisan test tests/Feature/AdaptiveInterfaceLayoutTest.php && npm run build`

Expected: PASS and Vite exits with code 0.

- [ ] **Step 5: Commit the web layout primitives**

```bash
git add tests/Feature/AdaptiveInterfaceLayoutTest.php resources/css/workspace.css
git commit -m "feat: add semantic adaptive layout primitives"
```

### Task 2: Classify every Blade page under a semantic layout family

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/public.blade.php`
- Modify: `resources/views/layouts/auth.blade.php`
- Modify: every page view under `resources/views/app`, `resources/views/admin`, `resources/views/site`, `resources/views/auth`, and `resources/views/errors`
- Modify: `tests/Feature/AdaptiveInterfaceLayoutTest.php`

- [ ] **Step 1: Extend the test with page-family coverage**

Add this method to `AdaptiveInterfaceLayoutTest`:

```php
#[Test]
public function every_page_view_declares_an_approved_layout_family(): void
{
    $roots = ['app', 'admin', 'site', 'auth', 'errors'];
    $allowed = ['dashboard', 'index', 'detail', 'form', 'wizard', 'report', 'board', 'reading', 'auth', 'status', 'marketing'];
    $views = [resource_path('views/home.blade.php'), resource_path('views/agency-reports/shared.blade.php')];

    foreach ($roots as $root) {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path("views/{$root}")));
        foreach ($iterator as $view) {
            $view = $view->getPathname();
            if (! str_ends_with($view, '.blade.php')) {
                continue;
            }
            if (str_contains($view, DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR)
                || str_contains(basename($view), '_')) {
                continue;
            }
            $views[] = $view;
        }
    }

    foreach ($views as $view) {
        $contents = file_get_contents($view);
        $this->assertMatchesRegularExpression(
            '/@section\([\'\"]layout[\'\"],\s*[\'\"]('.implode('|', $allowed).')[\'\"]\)/',
            $contents,
            $view,
        );
    }
}
```

- [ ] **Step 2: Run the test and verify unclassified views fail**

Run: `php artisan test tests/Feature/AdaptiveInterfaceLayoutTest.php`

Expected: FAIL with the first page view that lacks `@section('layout', ...)`.

- [ ] **Step 3: Make layouts expose the family as data and CSS classes**

In each layout, read the section once and emit it on the page root. The authenticated layout must use:

```blade
@php($layoutFamily = trim($__env->yieldContent('layout', 'index')))
<main id="main-content" class="panel__main layout-page layout-page--{{ in_array($layoutFamily, ['reading', 'auth'], true) ? 'reading' : (in_array($layoutFamily, ['form', 'wizard'], true) ? 'form' : 'operational') }}" data-layout="{{ $layoutFamily }}">
```

Use the same `data-layout` contract on the main content wrapper in `public.blade.php` and the form wrapper in `auth.blade.php`.

- [ ] **Step 4: Add an explicit layout section to every page**

Use this mapping without exceptions:

```text
dashboard: app/dashboard, admin/dashboard, admin/usage, app/billing/index, app/pulse/index
index: app/projects/index, app/tools/index, app/notifications/index, app/consultations/index,
       app/agency-reports/index, all admin index pages, admin/consultations/simulate
detail: app/projects/show, app/tools/show, app/geo/show, app/audience/lab,
        admin/tools/show, admin/manual/show, admin/consultations/show
form: app/projects/create, app/projects/edit, all admin form pages, admin/settings/index,
      admin/users/form, admin/users/bulk-plan, admin/users/plan-preview
wizard: app/runs/step, app/runs/review, app/runs/status, app/consultations/show, site/try/step
report: app/reports/show, app/agency-reports/show, site/try/result
board: app/tasks/index
marketing: site/tools/index, site/tools/show, home
reading: site/legal, agency-reports/shared
auth: all auth pages
status: errors/404
```

Each page gets exactly one declaration immediately after `@extends`, for example:

```blade
@section('layout', 'dashboard')
```

- [ ] **Step 5: Run coverage and existing route tests**

Run: `php artisan test tests/Feature/AdaptiveInterfaceLayoutTest.php tests/Feature/WebAppJourneyTest.php tests/Feature/AdminPanelTest.php tests/Feature/PublicHomePageTest.php tests/Feature/PublicToolJourneyTest.php`

Expected: PASS.

- [ ] **Step 6: Commit the page-family classification**

```bash
git add resources/views tests/Feature/AdaptiveInterfaceLayoutTest.php
git commit -m "refactor: classify every Blade page by layout family"
```

### Task 3: Apply the approved customer and admin structures

**Files:**
- Modify: `resources/css/workspace.css`
- Modify: `resources/views/app/dashboard.blade.php`
- Modify: `resources/views/admin/dashboard.blade.php`
- Modify: `resources/views/admin/usage.blade.php`
- Modify: `resources/views/app/projects/show.blade.php`
- Modify: `resources/views/app/runs/step.blade.php`
- Modify: `resources/views/app/reports/show.blade.php`
- Modify: `resources/views/app/tasks/index.blade.php`
- Test: `tests/Feature/AdaptiveInterfaceLayoutTest.php`

- [ ] **Step 1: Add rendered-layout assertions for representative page families**

Add assertions to the existing journey tests so the generated HTML contains these exact hooks:

```php
$response->assertSee('data-layout="dashboard"', false);
$response->assertSee('layout-metrics', false);
$response->assertSee('layout-main-aside', false);
```

For wizard, report, and board pages assert `data-layout="wizard"`, `layout-report`, and `data-layout="board"` respectively.

- [ ] **Step 2: Run the focused route tests and verify the hooks are absent**

Run: `php artisan test tests/Feature/WebAppJourneyTest.php tests/Feature/AdminPanelTest.php`

Expected: FAIL on the first missing semantic hook.

- [ ] **Step 3: Restructure dashboard metrics and primary content**

Replace `stat-row` with `layout-metrics` on customer and admin dashboards. On the admin dashboard keep users, live tools, completed analyses, and failed analyses in the primary row; move reports and AI cost into a secondary `layout-main-aside` section so no primary row exceeds four metrics.

Use this structure:

```blade
<section class="layout-metrics" aria-label="الملخص الأساسي">...</section>
<section class="layout-main-aside">
    <div class="layout-flow">...</div>
    <aside class="layout-aside" aria-label="ملخص إضافي">...</aside>
</section>
```

- [ ] **Step 4: Apply 8+4 and 9+3 structures**

- Project details: main history/content in the first child and actions/summary in `<aside class="layout-aside">`.
- Wizard: keep the existing 46rem+22rem behavior but rename the wrapper to include `layout-main-aside`.
- Report: score+summary stays 4+8, while the body uses `layout-report` with report content first and a section/source index second.
- Board: retain three desktop lanes and add a compact breakpoint that stacks grouped lanes.

- [ ] **Step 5: Add layout-flow and sticky-aside rules without changing decoration**

```css
.layout-flow { display: grid; gap: var(--layout-section-gap); min-inline-size: 0; }
.layout-aside { min-inline-size: 0; position: sticky; inset-block-start: 5.5rem; }
[data-layout="dashboard"] > .layout-metrics { order: 1; }
@media (max-width: 48rem) { .layout-aside { position: static; } }
```

- [ ] **Step 6: Run customer/admin route tests and build**

Run: `php artisan test tests/Feature/WebAppJourneyTest.php tests/Feature/AdminPanelTest.php tests/Feature/ReportChartsTest.php && npm run build`

Expected: PASS.

- [ ] **Step 7: Commit the authenticated layouts**

```bash
git add resources/css/workspace.css resources/views/app resources/views/admin tests/Feature
git commit -m "feat: apply adaptive layouts to customer and admin pages"
```

### Task 4: Make tables, forms, and internal windows responsive by intent

**Files:**
- Modify: `resources/css/workspace.css`
- Modify: `resources/views/admin/users/index.blade.php`
- Modify: `resources/views/admin/payments/index.blade.php`
- Modify: `resources/views/app/billing/index.blade.php`
- Modify: all form views under `resources/views/app/projects` and `resources/views/admin`
- Modify: dialog markup in `resources/views/app/consultations/show.blade.php` and admin confirmation actions
- Test: `tests/Feature/AdaptiveInterfaceLayoutTest.php`

- [ ] **Step 1: Add failing assertions for table and form modes**

```php
#[Test]
public function entity_tables_and_forms_declare_their_responsive_intent(): void
{
    foreach (['admin/users/index', 'admin/payments/index', 'app/billing/index'] as $view) {
        $this->assertStringContainsString('data-table="entity"', file_get_contents(resource_path("views/{$view}.blade.php")));
    }

    $css = file_get_contents(resource_path('css/workspace.css'));
    $this->assertStringContainsString('[data-table="entity"]', $css);
    $this->assertStringContainsString('.form-layout', $css);
    $this->assertStringContainsString('.dialog--confirm', $css);
    $this->assertStringContainsString('.drawer--detail', $css);
}
```

- [ ] **Step 2: Run the test and verify it fails**

Run: `php artisan test tests/Feature/AdaptiveInterfaceLayoutTest.php`

Expected: FAIL because entity modes do not exist.

- [ ] **Step 3: Add explicit table modes**

Mark entity tables with `data-table="entity"` and add `data-label` to every body cell. Keep analytical and bulk-selection tables as `data-table="matrix"` inside `.table-wrap`.

At `max-width: 48rem`, render entity rows as grid blocks and each cell as a two-column label/value row using `td::before { content: attr(data-label); }`. Matrix tables retain local horizontal overflow and use a sticky first column.

- [ ] **Step 4: Constrain form rows**

Add `.form-layout` around forms and use `.field-row` for no more than two short fields. Ensure textarea, upload, checkbox matrices, prompt editors, and embedded tables use `grid-column: 1 / -1`; at 48rem every field spans the full row.

- [ ] **Step 5: Size windows by task**

Add only structural rules:

```css
.dialog--confirm { inline-size: min(100%, 30rem); }
.dialog--edit { inline-size: min(100%, 42rem); }
.drawer--detail { inline-size: min(100%, 30rem); }
@media (max-width: 48rem) { .drawer--detail { inline-size: 100%; } }
```

Long forms remain full pages and are not moved into dialogs.

- [ ] **Step 6: Run focused tests and Vite build**

Run: `php artisan test tests/Feature/AdaptiveInterfaceLayoutTest.php tests/Feature/AdminPanelTest.php tests/Feature/CheckoutTest.php tests/Feature/ConsultationLifecycleTest.php && npm run build`

Expected: PASS.

- [ ] **Step 7: Commit responsive data-entry patterns**

```bash
git add resources/css/workspace.css resources/views tests/Feature/AdaptiveInterfaceLayoutTest.php
git commit -m "feat: make tables forms and windows responsive by intent"
```

### Task 5: Align public, authentication, legal, and error pages

**Files:**
- Modify: `resources/css/site-pages.css`
- Modify: `resources/css/workspace.css`
- Modify: `resources/views/home.blade.php`
- Modify: `resources/views/site/tools/index.blade.php`
- Modify: `resources/views/site/tools/show.blade.php`
- Modify: `resources/views/site/try/step.blade.php`
- Modify: `resources/views/site/try/result.blade.php`
- Modify: `resources/views/site/legal.blade.php`
- Modify: `resources/views/layouts/auth.blade.php`
- Modify: `resources/views/errors/404.blade.php`
- Test: `tests/Feature/PublicHomePageTest.php`
- Test: `tests/Feature/PublicToolJourneyTest.php`

- [ ] **Step 1: Add public-layout assertions**

Assert `data-layout="marketing"` on home and tools, `data-layout="wizard"` on guest steps, `data-layout="reading"` on legal, `data-layout="auth"` on login, and `data-layout="status"` on 404.

- [ ] **Step 2: Run public tests and verify the new hooks fail**

Run: `php artisan test tests/Feature/PublicHomePageTest.php tests/Feature/PublicToolJourneyTest.php tests/Feature/GuestTrialTest.php`

Expected: FAIL on missing layout hooks.

- [ ] **Step 3: Normalize public grids without changing visual tokens**

- Home hero: 7+5 on desktop, one column below 900px.
- Public catalog: exactly 3/2/1 columns.
- Tool detail: 7+5 hero and 4/2/1 steps.
- Guest wizard: reading/form width up to 52rem.
- Legal: 46rem reading width.
- Auth: 30rem focused form.
- Error: one-column status with a single primary return action.

Reuse existing borders, radii, shadows, and colors unchanged.

- [ ] **Step 4: Run public tests and build**

Run: `php artisan test tests/Feature/PublicHomePageTest.php tests/Feature/PublicToolJourneyTest.php tests/Feature/GuestTrialTest.php && npm run build`

Expected: PASS.

- [ ] **Step 5: Commit public layout alignment**

```bash
git add resources/css resources/views tests/Feature
git commit -m "feat: align public and auth page layouts"
```

### Task 6: Add tested Flutter adaptive layout primitives

**Files:**
- Create: `mobile/lib/core/widgets/adaptive_layout.dart`
- Create: `mobile/test/core/widgets/adaptive_layout_test.dart`
- Modify: `mobile/lib/core/widgets/common.dart`

- [ ] **Step 1: Write failing widget tests**

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:khaledsaad_app/core/widgets/adaptive_layout.dart';

void main() {
  testWidgets('AdaptiveSplit stacks the aside after content on phones', (tester) async {
    await tester.binding.setSurfaceSize(const Size(390, 844));
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: AdaptiveSplit(main: Text('main'), aside: Text('aside')))));
    final main = tester.getTopLeft(find.text('main'));
    final aside = tester.getTopLeft(find.text('aside'));
    expect(aside.dy, greaterThan(main.dy));
  });

  testWidgets('AdaptiveMetricGrid uses four columns on expanded widths', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1280, 900));
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: AdaptiveMetricGrid(children: List.generate(4, (i) => Text('$i'))))));
    expect(tester.getTopLeft(find.text('0')).dy, tester.getTopLeft(find.text('3')).dy);
  });
}
```

- [ ] **Step 2: Run the test and verify imports fail**

Run: `flutter test test/core/widgets/adaptive_layout_test.dart`

Expected: FAIL because `adaptive_layout.dart` does not exist.

- [ ] **Step 3: Implement shared Flutter primitives**

Create `AdaptivePage`, `AdaptiveSplit`, `AdaptiveMetricGrid`, and `AdaptiveActionBar` using `LayoutBuilder`:

```dart
const compactBreakpoint = 600.0;
const expandedBreakpoint = 1024.0;

class AdaptiveSplit extends StatelessWidget {
  const AdaptiveSplit({super.key, required this.main, required this.aside});
  final Widget main;
  final Widget aside;

  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, constraints) {
    if (constraints.maxWidth < compactBreakpoint) {
      return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [main, const SizedBox(height: 24), aside]);
    }
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Expanded(flex: 2, child: main), const SizedBox(width: 24), Expanded(child: aside)]);
  });
}
```

`AdaptivePage` centers content and selects max widths equivalent to reading, form, and operational families. `AdaptiveMetricGrid` uses 2 columns compact, 2 medium, and 4 expanded. `AdaptiveActionBar` stacks its buttons on compact widths.

- [ ] **Step 4: Run focused tests and analysis**

Run: `flutter test test/core/widgets/adaptive_layout_test.dart && flutter analyze`

Expected: PASS with no analysis issues.

- [ ] **Step 5: Commit Flutter primitives**

```bash
git add mobile/lib/core/widgets mobile/test/core/widgets
git commit -m "feat: add Flutter adaptive layout primitives"
```

### Task 7: Migrate every Flutter screen to the shared layout contract

**Files:**
- Modify: all 22 files matching `mobile/lib/features/**/*_screen.dart`
- Modify: `mobile/test/widget_test.dart`
- Test: `mobile/test/core/widgets/adaptive_layout_test.dart`
- Test: `mobile/test/widget_test.dart`

- [ ] **Step 1: Add a source-coverage test**

In `adaptive_layout_test.dart`, add a filesystem test that enumerates `lib/features/**/*_screen.dart`, skips generated files, and asserts every screen imports `core/widgets/adaptive_layout.dart` or a feature-local wrapper that imports it.

- [ ] **Step 2: Run the coverage test and verify existing screens fail**

Run: `flutter test test/core/widgets/adaptive_layout_test.dart`

Expected: FAIL listing the first unmigrated screen.

- [ ] **Step 3: Migrate screens by family**

Apply these exact wrappers:

```text
reading: public/legal_screen, public/shared_report_screen, reports/report_screen,
         agency_reports/agency_report_screen
form: auth/auth_screen, auth/password_reset_request_screen, auth/password_reset_screen,
      projects/project_form_screen
dashboard: projects/dashboard_screen, growth/growth_hub_screen, admin/admin_hub_screen,
           account/billing_screen
index: tools/tool_catalog_screen, agency_reports/agency_reports_screen,
       account/notifications_screen, projects/tasks_screen
detail: public/public_tool_screen, projects/project_screen
wizard: consultations/consultation_screen, tools/run_wizard_screen, tools/run_status_screen
marketing: public/public_home_screen
```

Replace hardcoded outer `EdgeInsets.all(16|20)` with `AdaptivePage` padding, keep existing inner spacing, and use `AdaptiveSplit` only where a real main/aside relationship exists. Do not change repository calls, navigation, text, or colors.

- [ ] **Step 4: Add compact and expanded widget assertions**

Pump dashboard, project, wizard, and report screens at 390px and 1024px. Assert no exception, primary actions remain present, and main content precedes contextual content on compact widths.

- [ ] **Step 5: Run all Flutter checks**

Run: `flutter analyze && flutter test`

Expected: PASS.

- [ ] **Step 6: Commit the Flutter screen migration**

```bash
git add mobile/lib mobile/test
git commit -m "refactor: migrate Flutter screens to adaptive layouts"
```

### Task 8: Apply the print layout contract

**Files:**
- Modify: `resources/views/reports/pdf.blade.php`
- Modify: `resources/views/agency-reports/pdf.blade.php`
- Modify: `resources/views/agency-reports/partials/document.blade.php`
- Modify: `tests/Feature/ReportPdfParityTest.php`
- Modify: `tests/Feature/AgencyReportDeliveryTest.php`

- [ ] **Step 1: Add failing print-contract assertions**

Assert rendered HTML contains `class="print-report"`, `class="print-section"`, `break-inside: avoid`, and a print table rule that does not use horizontal overflow.

- [ ] **Step 2: Run PDF tests and verify the hooks fail**

Run: `php artisan test tests/Feature/ReportPdfParityTest.php tests/Feature/AgencyReportDeliveryTest.php`

Expected: FAIL on missing print classes.

- [ ] **Step 3: Add semantic print wrappers and page-break rules**

Use `.print-report` on the root, `.print-section` on logical sections, and `.print-table` on tables. Add:

```css
.print-section { break-inside: avoid; }
.print-section--long { break-inside: auto; }
.print-table { inline-size: 100%; table-layout: fixed; border-collapse: collapse; }
.print-table th, .print-table td { overflow-wrap: anywhere; }
```

Do not change report content or colors.

- [ ] **Step 4: Run PDF tests**

Run: `php artisan test tests/Feature/ReportPdfParityTest.php tests/Feature/AgencyReportDeliveryTest.php tests/Feature/AgencySampleRenderTest.php`

Expected: PASS.

- [ ] **Step 5: Commit print layouts**

```bash
git add resources/views/reports resources/views/agency-reports tests/Feature
git commit -m "feat: add semantic print layout contract"
```

### Task 9: Audit full coverage and verify the release gate

**Files:**
- Modify: `config/product_quality.php`
- Modify: `tests/Unit/ProductQuality/ParityMatrixTest.php`
- Modify: `docs/product-quality-matrix.md`
- Test: full Laravel and Flutter suites

- [ ] **Step 1: Add layout evidence to the product-quality matrix**

Add records for public, customer, admin, Flutter, and PDF layout coverage. Each record must point to its page-family test, responsive CSS/Flutter primitive, and verification command.

- [ ] **Step 2: Run the matrix test and fix only missing evidence links**

Run: `php artisan test tests/Unit/ProductQuality/ParityMatrixTest.php`

Expected: PASS with every new record containing web, API/non-applicable, and mobile evidence as required by the existing schema.

- [ ] **Step 3: Run formatting and static checks**

Run:

```text
vendor/bin/pint --test
npm run build
flutter analyze
```

Expected: all exit 0.

- [ ] **Step 4: Run complete automated tests**

Run:

```text
php artisan test
flutter test
```

Expected: all tests pass, zero failures.

- [ ] **Step 5: Inspect responsive high-risk pages**

Render home, app dashboard, admin dashboard, users table, project form, run wizard, report, billing, and Flutter dashboard/report at 320, 360, 390, 768, 1024, 1280, and 1440px. Confirm no page-level horizontal scroll, clipped actions, overlapping text, or misplaced RTL order.

- [ ] **Step 6: Run the final diff and secret checks**

Run:

```text
git diff --check main...HEAD
git status --short
git diff --name-only main...HEAD
```

Expected: no whitespace errors, only planned files, and no secrets or generated build artifacts staged.

- [ ] **Step 7: Commit quality evidence**

```bash
git add config/product_quality.php tests/Unit/ProductQuality/ParityMatrixTest.php docs/product-quality-matrix.md
git commit -m "test: record adaptive layout coverage"
```
