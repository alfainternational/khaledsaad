# Unified Interface System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Apply the approved multi-family visual system to every human-facing Laravel and Flutter interface while preserving copy, behavior, permissions, data, RTL, and Latin digits.

**Architecture:** A shared semantic token and component layer powers six scoped families: public editorial, authentication, workspace, administration, reports, and native Flutter. Coverage is enforced from filesystem inventories and route/screen contracts; public artwork is tracked by a one-placement manifest.

**Tech Stack:** Laravel 13, Blade, CSS/Vite, PHPUnit, Flutter/Dart, Material 3, widget tests, Android Gradle.

---

## Execution Safety

The repository contains pre-existing uncommitted work, including files this feature must extend. Preserve it, inspect every overlapping diff, and do not create implementation commits that would accidentally include unrelated changes. Use targeted tests and targeted staging only if a clean task-owned file set exists.

### Task 1: Coverage contracts

**Files:**
- Create: `config/interface.php`
- Create: `tests/Feature/UnifiedInterfaceCoverageTest.php`
- Modify: `tests/Feature/AdaptiveInterfaceLayoutTest.php`

- [ ] **Step 1: Write failing coverage tests**

Add tests that enumerate human-facing Blade pages, require an approved layout marker, require all six family names in configuration, reject `HacenTunisia` in Flutter Dart files, reject Arabic-Indic digits in static UI copy, and assert that every public `data-section-art` value is unique and exists.

- [ ] **Step 2: Verify RED**

Run:

```powershell
php artisan test tests/Feature/UnifiedInterfaceCoverageTest.php
```

Expected: failure because `config/interface.php` and the global `data-interface-system="v2"` markers do not exist.

- [ ] **Step 3: Add the coverage registry**

Create `config/interface.php` with these families and roots:

```php
return [
    'version' => 'v2',
    'families' => [
        'public' => ['resources/views/home.blade.php', 'resources/views/site'],
        'auth' => ['resources/views/auth'],
        'workspace' => ['resources/views/app'],
        'admin' => ['resources/views/admin'],
        'reports' => ['resources/views/reports', 'resources/views/agency-reports'],
        'flutter' => ['mobile/lib'],
    ],
    'excluded_blade_segments' => ['/components/', '/partials/', '/vendor/'],
];
```

- [ ] **Step 4: Keep the tests RED for missing layout integration**

Run the same test and confirm it now fails only on missing interface markers or Flutter font migration.

### Task 2: Shared web foundation

**Files:**
- Create: `resources/css/interface-system.css`
- Create: `resources/views/components/ui/icon.blade.php`
- Create: `resources/views/components/ui/page-header.blade.php`
- Create: `resources/views/components/ui/empty-state.blade.php`
- Create: `resources/views/components/ui/metric.blade.php`
- Modify: `resources/css/app.css`
- Modify: `resources/views/layouts/public.blade.php`
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/auth.blade.php`
- Test: `tests/Feature/UnifiedInterfaceCoverageTest.php`

- [ ] **Step 1: Add a failing rendered-layout assertion**

Assert public, auth, workspace, and admin responses contain `data-interface-system="v2"` and the correct `data-interface-family` value.

- [ ] **Step 2: Verify RED**

Run the coverage test and confirm the marker assertion fails.

- [ ] **Step 3: Implement the shared markers and token layer**

Add the marker attributes to the three layouts. Import `interface-system.css` last. Define semantic tokens from `STYLESEED.md`, IBM Plex typography, focus, touch targets, ruled surfaces, page headers, icon circles, metric basis/coverage, empty/error/loading states, responsive containment, and light/dark equivalents. Scope every family through `[data-interface-family]` and avoid direct component hex colors.

- [ ] **Step 4: Implement semantic Blade primitives**

Create components with explicit slots for titles, descriptions, metadata, evidence, basis, and actions. Keep them presentation-only so existing controllers and forms remain unchanged.

- [ ] **Step 5: Verify GREEN**

Run the coverage and adaptive-layout tests.

### Task 3: Public editorial family

**Files:**
- Modify: `resources/views/partials/site-header.blade.php`
- Modify: `resources/views/partials/site-footer.blade.php`
- Modify: `resources/views/site/pages/show.blade.php`
- Modify: `resources/views/site/pages/profile.blade.php`
- Modify: `resources/views/site/pricing.blade.php`
- Modify: `resources/views/site/sectors/index.blade.php`
- Modify: `resources/views/site/sectors/show.blade.php`
- Modify: `resources/views/site/tools/index.blade.php`
- Modify: `resources/views/site/tools/show.blade.php`
- Modify: `resources/views/site/try/step.blade.php`
- Modify: `resources/views/site/try/result.blade.php`
- Modify: `resources/views/site/content/index.blade.php`
- Modify: `resources/views/site/content/show.blade.php`
- Modify: `resources/views/site/legal.blade.php`
- Modify: `resources/views/errors/*.blade.php`
- Create: `config/interface_artwork.php`
- Test: `tests/Feature/PublicInterfaceCoverageTest.php`

- [ ] **Step 1: Write route-family contract tests**

Render representative public routes and assert the editorial family marker, full-width heading region, semantic row icons, preserved copy, RTL, Latin digits, and no repeated design asset sources.

- [ ] **Step 2: Verify RED**

Run the public interface test and confirm legacy pages lack the editorial contract.

- [ ] **Step 3: Create the artwork placement manifest**

Map each existing or generated public raster asset to exactly one route/section. Do not assign art to a page when semantic icons and typography are sufficient.

- [ ] **Step 4: Recompose public templates**

Use physical-right copy and physical-left visual regions on desktop, stacked copy-first flow on mobile, ruled rows instead of equal card grids, compact headings using IBM Plex, one primary action, and existing copy/links/conditions.

- [ ] **Step 5: Verify all public routes**

Run public, sector, content, try-flow, and legal tests, then build Vite.

### Task 4: Authentication, workspace, and administration

**Files:**
- Modify: `resources/views/auth/*.blade.php`
- Modify: `resources/views/partials/panel-nav.blade.php`
- Modify: `resources/views/app/**/*.blade.php`
- Modify: `resources/views/admin/**/*.blade.php`
- Modify: `resources/css/workspace.css`
- Test: `tests/Feature/UnifiedWorkspaceInterfaceTest.php`
- Test: `tests/Feature/UnifiedAdminInterfaceTest.php`

- [ ] **Step 1: Write failing family and component tests**

For representative authorized routes, assert page header hierarchy, one primary action region, semantic status/icons, accessible table/form structure, empty/error state hooks, and correct workspace/admin density marker.

- [ ] **Step 2: Verify RED**

Run the two interface tests and confirm representative legacy templates fail the component contract.

- [ ] **Step 3: Apply shared operational structure**

Normalize existing headings/actions into the page-header pattern, flatten card grids into ruled panels where hierarchy is equal, keep tables dense but readable, preserve every form name/action/method, and keep permission/destructive behavior unchanged.

- [ ] **Step 4: Cover all workspace and admin files**

Use the filesystem coverage test to prevent any top-level human-facing view from remaining outside the approved layouts. Validate long titles, empty states, errors, tables, wizards, and dialogs.

- [ ] **Step 5: Verify GREEN**

Run adaptive-layout, authorization, admin, consultation, reporting, project, learning, billing, and workspace feature tests.

### Task 5: Reports and PDFs

**Files:**
- Modify: `resources/views/reports/*.blade.php`
- Modify: `resources/views/agency-reports/**/*.blade.php`
- Modify: `resources/views/app/reports/show.blade.php`
- Modify: `resources/views/app/agency-reports/*.blade.php`
- Modify: `resources/views/site/try/result.blade.php`
- Modify: `resources/css/interface-system.css`
- Test: `tests/Feature/ReportPdfParityTest.php`
- Test: `tests/Feature/AgencyPortfolioTest.php`

- [ ] **Step 1: Extend failing report-order/parity tests**

Assert executive summary precedes findings, score is adjacent to basis/coverage/evidence, screen/shared/PDF headings remain aligned, and PDFs embed IBM Plex fonts.

- [ ] **Step 2: Verify RED**

Run report and agency tests and confirm at least one legacy report lacks the unified report marker.

- [ ] **Step 3: Apply documentary report structure**

Preserve report data and conditions while aligning summary, evidence, findings, recommendations, tasks, tables, and print page-break rules. Do not reuse public raster art.

- [ ] **Step 4: Verify GREEN**

Run report, agency, shared-report, and PDF tests.

### Task 6: Native Flutter foundation and all screens

**Files:**
- Copy: `public/assets/fonts/IBMPlexSansArabic-Regular.ttf` to `mobile/assets/fonts/IBMPlexSansArabic-Regular.ttf`
- Copy: `public/assets/fonts/IBMPlexSansArabic-Bold.ttf` to `mobile/assets/fonts/IBMPlexSansArabic-Bold.ttf`
- Modify: `mobile/pubspec.yaml`
- Modify: `mobile/lib/core/theme/app_theme.dart`
- Modify: `mobile/lib/core/widgets/common.dart`
- Modify: all files matching `mobile/lib/**/*_screen.dart`
- Modify: `mobile/lib/features/public/public_shell.dart`
- Test: `mobile/test/core/widgets/unified_interface_test.dart`
- Test: existing `mobile/test/**/*.dart`

- [ ] **Step 1: Write failing Flutter theme/widget tests**

Assert the active theme uses `IBMPlexSansArabic`, representative numbers render as Latin digits, shared metric/status/empty/action widgets expose RTL semantics, and no Dart file contains `HacenTunisia`.

- [ ] **Step 2: Verify RED**

Run the focused Flutter tests and confirm the font assertion fails.

- [ ] **Step 3: Migrate font assets and theme**

Declare regular and bold IBM Plex assets, update `ThemeData` and text theme weights, preserve current color semantics through unified tokens, and remove Hacen from interface text.

- [ ] **Step 4: Expand shared widgets**

Implement native page headers, ruled panels/rows, metric basis/coverage/evidence, status, empty/error/loading states, and bottom action bars in `common.dart` or focused files imported by it.

- [ ] **Step 5: Migrate all 37 Flutter screens**

Replace ad-hoc headings, containers, empty states, and action rows with shared semantic widgets without changing API requests, models, routes, or permissions.

- [ ] **Step 6: Verify GREEN**

Run `dart format`, `flutter analyze`, focused tests, then the complete Flutter test suite.

### Task 7: Responsive and visual audit

**Files:**
- Modify: `resources/css/interface-system.css`
- Modify: family templates only when a measured issue requires structural correction
- Test: `tests/Feature/AdaptiveInterfaceLayoutTest.php`

- [ ] **Step 1: Add measurable responsive contracts**

Assert the approved family hooks exist for mobile navigation, table containment, artwork containment, action bars, reduced motion, and print rules.

- [ ] **Step 2: Browser audit**

Inspect representative public, auth, workspace, admin, report, error, empty, long-table, and long-form routes at 390, 768, 1280, and 1440 widths. Record computed font floors, overflow, focus/touch targets, missing images, and console errors.

- [ ] **Step 3: Correct measured failures**

Change only the family rule or structural template responsible for each measured failure, then rerun its focused test and browser check.

### Task 8: Final verification and release artifacts

**Files:**
- Update: `docs/product/interface-coverage.yaml`
- Build output: `mobile/build/app/outputs/flutter-apk/app-release.apk`
- Build output: `mobile/build/app/outputs/bundle/release/app-release.aab`

- [ ] **Step 1: Run Laravel verification**

Run interface-specific tests, full relevant feature groups, Vite build, view compilation, and `git diff --check`.

- [ ] **Step 2: Run Flutter verification**

Run format check, analyze, complete tests, APK build, and AAB build with the production API/build defines already used by the repository.

- [ ] **Step 3: Independently verify artifacts**

Record exact paths, package, versionName/versionCode, signing certificate details, file sizes, and SHA-256 hashes. Do not treat file existence as release proof.

- [ ] **Step 4: Report only the deployment boundary**

Return to the user only after every local interface and artifact check passes, stating that production web/mobile publication is the remaining external action.
