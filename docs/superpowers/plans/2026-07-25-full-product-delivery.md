# Full Web and Mobile Product Delivery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver one production release in which the responsive web experience, native Flutter application, neutral Arabic copy, report quality, Firebase integration, signed Android download, and production hosting pass the acceptance gate defined in the approved design.

**Architecture:** Laravel remains the single source of truth and exposes versioned API contracts to Blade and Flutter. A machine-readable parity ledger links every visitor, customer, and administrator capability to its web route, API route, Flutter screen, UI states, and automated evidence. Work is split into independently testable plans, but completion and production deployment remain one gate.

**Tech Stack:** PHP 8.3, Laravel 13, PHPUnit 12, Blade, Vite 7, Flutter 3.44, Dart 3.12, Firebase, Android Gradle, LiteSpeed/cPanel.

---

## File structure and plan boundaries

- `docs/product/parity-matrix.yaml`: JSON-compatible YAML ledger for all product capabilities.
- `docs/product/arabic-style-guide.md`: approved neutral-Arabic voice and terminology.
- `app/Support/ProductQuality/*`: source scanners and parity validation used by tests and the audit command.
- `tests/Unit/ProductQuality/*`: fast deterministic checks for parity and language.
- `tests/Feature/*ParityTest.php`: Laravel web/API contract and authorization evidence.
- `tests/Browser/*`: responsive browser journeys and screenshot assertions.
- `mobile/lib/features/public_site/*`: visitor experience.
- `mobile/lib/features/admin/*`: administrator experience.
- `mobile/lib/features/growth/*`: Pulse, GEO, audience, tasks, and KPI parity.
- `mobile/lib/core/firebase/*`: Firebase startup, messaging, analytics policy, and token lifecycle.
- `mobile/test/parity/*`: Flutter contract and journey evidence.
- `app/Services/Tools/V2/*`: versioned report-claim validation and repair.
- `public/downloads/*`: versioned APK files and release manifest; generated binaries remain release artifacts.
- `resources/views/site/download.blade.php`: public download page.
- `deploy/*`: repeatable release, smoke, checksum, backup, and rollback scripts.

Each subsystem below gets a detailed child plan before its implementation begins. The child plan must use test-first steps and must update the same parity ledger instead of creating a second source of truth.

### Task 1: Establish the measurable parity and language foundation

**Child plan:** `docs/superpowers/plans/2026-07-25-parity-language-foundation.md`

**Files:**
- Create: `docs/product/parity-matrix.yaml`
- Create: `docs/product/arabic-style-guide.md`
- Create: `app/Support/ProductQuality/ParityMatrix.php`
- Create: `app/Support/ProductQuality/NeutralArabicScanner.php`
- Create: `app/Console/Commands/AuditProductQuality.php`
- Create: `tests/Unit/ProductQuality/ParityMatrixTest.php`
- Create: `tests/Unit/ProductQuality/NeutralArabicScannerTest.php`
- Create: `tests/Feature/ProductQualityCommandTest.php`
- Modify: user-visible copy reported by the scanner.

- [ ] Write a failing matrix test requiring unique capability IDs, all required evidence fields, allowed roles/states, and proof for every `verified` record.
- [ ] Run `php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\ProductQuality\ParityMatrixTest.php` and confirm it fails because the matrix reader does not exist.
- [ ] Implement the matrix reader and complete visitor, customer, and administrator records from current routes.
- [ ] Write a failing scanner test with the prohibited sentence `الصفحة دي ما موجودة`.
- [ ] Implement line-aware scanning of user-visible PHP, Blade, and Dart strings.
- [ ] Add the approved glossary and prohibited dialect patterns.
- [ ] Correct every current scanner finding without changing business meaning.
- [ ] Add `php artisan product:audit` and a feature test proving a non-zero exit code for invalid fixtures and zero for repository sources.
- [ ] Run the three focused test files and commit only foundation files and intentional copy changes.

### Task 2: Complete Laravel API contracts for every role

**Child plan:** `docs/superpowers/plans/2026-07-25-api-contract-parity.md`

**Files:**
- Modify: `routes/api.php`
- Create or modify: `app/Http/Controllers/Api/V1/Public/*`
- Create or modify: `app/Http/Controllers/Api/V1/Admin/*`
- Modify: existing V1 customer controllers.
- Create: `app/Http/Resources/Api/V1/*`
- Create: `tests/Feature/PublicApiParityTest.php`
- Create: `tests/Feature/CustomerApiParityTest.php`
- Create: `tests/Feature/AdminApiParityTest.php`

- [ ] Generate a failing route-coverage test from `docs/product/parity-matrix.yaml`.
- [ ] Add public content, guest journey, password reset, download manifest, and shared-report contracts.
- [ ] Add missing customer Pulse, GEO, audience, task, KPI, report, billing, and notification contracts.
- [ ] Add administrator contracts with server-side `admin` middleware, policies, validation, and 404 ownership rules.
- [ ] Normalize success, validation, authorization, and error envelopes without breaking existing mobile consumers.
- [ ] Run route coverage, ownership, authorization, and current API tests.
- [ ] Mark only proven API evidence as verified in the matrix and commit.

### Task 3: Make every web page responsive and accessible

**Child plan:** `docs/superpowers/plans/2026-07-25-responsive-web-accessibility.md`

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/css/site-pages.css`
- Modify: `resources/css/workspace.css`
- Modify: affected Blade layouts and views.
- Create: `tests/Browser/responsive.spec.js`
- Create: `tests/Browser/accessibility.spec.js`
- Modify: `package.json`

- [ ] Add Playwright and a test that initially fails on horizontal overflow at one known narrow page.
- [ ] Cover every public, authenticated customer, and administrator GET page at 320, 360, 375, 390, 412, 768, 1024, 1280, and 1440 pixels.
- [ ] Fix overflow, navigation, tables, dialogs, forms, charts, reports, and touch targets.
- [ ] Add keyboard, focus, reduced-motion, accessible-name, contrast, and 200% text checks.
- [ ] Store screenshot baselines inside the repository test artifact path.
- [ ] Run browser tests, Vite build, Pint, and related Laravel journeys.
- [ ] Update responsive evidence in the matrix and commit.

### Task 4: Build the native visitor and authentication experience

**Child plan:** `docs/superpowers/plans/2026-07-25-flutter-public-auth.md`

**Files:**
- Create: `mobile/lib/features/public_site/*`
- Modify: `mobile/lib/features/auth/*`
- Create: `mobile/lib/core/navigation/*`
- Split: `mobile/lib/core/api/platform_repository.dart` into domain repositories.
- Create: `mobile/test/parity/public_journey_test.dart`
- Create: `mobile/test/parity/auth_journey_test.dart`

- [ ] Write failing Flutter contract tests for homepage, tools, guest flow, legal pages, shared reports, password reset, and deep links.
- [ ] Build visitor and authentication shells using the API contracts.
- [ ] Preserve guest progress through registration and route the user to the same destination as the web.
- [ ] Add loading, empty, offline, validation, expired-session, and retry states.
- [ ] Verify RTL, text scaling, semantic labels, and deep links.
- [ ] Run Flutter analysis/tests and Laravel public/auth contract tests.
- [ ] Update matrix evidence and commit.

### Task 5: Complete the native customer experience

**Child plan:** `docs/superpowers/plans/2026-07-25-flutter-customer-parity.md`

**Files:**
- Modify: `mobile/lib/features/projects/*`
- Modify: `mobile/lib/features/tools/*`
- Modify: `mobile/lib/features/reports/*`
- Modify: `mobile/lib/features/agency_reports/*`
- Create: `mobile/lib/features/growth/*`
- Modify: `mobile/lib/features/account/*`
- Create: `mobile/test/parity/customer_journeys_test.dart`

- [ ] Generate failing tests for every customer matrix record.
- [ ] Complete projects, all eleven tools, conditional fields, files, preflight, hybrid insights, queue, retry, and manual review.
- [ ] Complete reports, evidence, assumptions, comparison, feedback, watch, tasks, PDF, and agency reports.
- [ ] Complete competitors, Pulse, GEO, audience, KPI, notifications, billing, checkout, and credit ledger.
- [ ] Make entitlement-denied and ownership states match the web.
- [ ] Run Flutter analysis/tests and all corresponding Laravel contract tests.
- [ ] Update matrix evidence and commit.

### Task 6: Complete the native administrator experience

**Child plan:** `docs/superpowers/plans/2026-07-25-flutter-admin-parity.md`

**Files:**
- Create: `mobile/lib/features/admin/*`
- Create: `mobile/test/parity/admin_journeys_test.dart`
- Modify: session/user models to expose server-authoritative roles.

- [ ] Write failing tests for every administrator matrix record.
- [ ] Add an admin shell visible only when the server returns admin capability.
- [ ] Implement usage, tools, versions, prompts, features, plans, packs, gateways, users, credits, payments, manual reports, and settings.
- [ ] Require destructive or financial actions to show explicit confirmation and server response.
- [ ] Verify a non-admin cannot access endpoints even if navigation is forged.
- [ ] Run Flutter and Laravel administrator tests.
- [ ] Update matrix evidence and commit.

### Task 7: Integrate Firebase and production mobile identity

**Child plan:** `docs/superpowers/plans/2026-07-25-firebase-mobile-release.md`

**Files:**
- Modify: `mobile/pubspec.yaml`
- Modify: `mobile/android/app/build.gradle.kts`
- Modify: `mobile/ios/Runner.xcodeproj/project.pbxproj`
- Add: provided Firebase platform configuration files.
- Create: `mobile/lib/firebase_options.dart`
- Create: `mobile/lib/core/firebase/*`
- Create: `app/Models/DeviceToken.php`
- Create: migration and API controller for token lifecycle.
- Create: Flutter and Laravel Firebase tests.

- [ ] Write failing identity tests for Android package, iOS bundle, Firebase project IDs, and production API default.
- [ ] Apply `net.khaledsaad.ksgrowth_mobile` and `net.khaledsaad.ksgrowthMobile`.
- [ ] Add Core, Analytics, Crashlytics, Messaging, Performance, and Remote Config.
- [ ] Enforce an analytics allowlist that excludes answers, reports, files, email, phone, and payment data.
- [ ] Register, refresh, and revoke device tokens through authenticated API routes.
- [ ] Test foreground/background notification routing and session logout cleanup.
- [ ] Run Flutter analysis/tests and Android debug build before release signing.
- [ ] Update matrix evidence and commit.

### Task 8: Upgrade report prompts and semantic validation

**Child plan:** `docs/superpowers/plans/2026-07-25-report-prompt-v2.md`

**Files:**
- Create: `app/Services/Tools/V2/*`
- Create: version-2 tool definition data without mutating locked versions.
- Modify: `app/Services/Tools/ToolRunPipeline.php`
- Create: `tests/Feature/PromptV2EvaluationTest.php`
- Create: `tests/Fixtures/prompt-v2/*`

- [ ] Write failing tests for unsupported numbers, claim types, formula provenance, prompt injection, contradiction repair, and maximum primary recommendations.
- [ ] Move funnel, budget, margin, channel-capacity, and campaign calculations to deterministic services.
- [ ] Add fact/calculation/benchmark/hypothesis claim schemas and tool-specific synthesis schemas.
- [ ] Add semantic validation and repair before final report persistence.
- [ ] Build at least eight evaluation fixtures for each of eleven tools.
- [ ] Run V1/V2 shadow comparison and promote only after the V2 acceptance thresholds pass.
- [ ] Preserve historical report version references and commit.

### Task 9: Sign Android and publish the download experience

**Child plan:** `docs/superpowers/plans/2026-07-25-android-download-release.md`

**Files:**
- Modify: `mobile/android/app/build.gradle.kts`
- Modify: `.gitignore`
- Create: `app/Support/Releases/MobileReleaseManifest.php`
- Create: `app/Http/Controllers/Site/AppDownloadController.php`
- Modify: `routes/web.php`
- Create: `resources/views/site/download.blade.php`
- Create: `tests/Feature/AppDownloadTest.php`
- Create: `deploy/build-mobile-release.ps1`

- [ ] Write failing tests for `/download`, manifest metadata, versioned APK path, SHA-256, and missing-artifact behavior.
- [ ] Configure release signing from ignored local properties or environment variables; never use debug signing for release.
- [ ] Build release APK and AAB from the production API base URL.
- [ ] Generate a versioned manifest and checksum.
- [ ] Publish the APK through the download page and expose the store link only when configured.
- [ ] Install the APK on a clean Android target and run public/login/customer smoke journeys.
- [ ] Commit source/configuration changes, excluding signing secrets and generated binaries from Git unless the release policy explicitly tracks them.

### Task 10: Execute production deployment and completion audit

**Child plan:** `docs/superpowers/plans/2026-07-25-production-release-audit.md`

**Files:**
- Modify or create: `deploy/*`
- Create: `docs/product/release-evidence/<version>/*`
- Verify production server and database.

- [ ] Rotate the exposed SSH credential and verify the replacement before deployment.
- [ ] Back up production database, `.env`, uploaded files, and the current release.
- [ ] Run Laravel tests, Pint, Vite, browser tests, Flutter analysis/tests, APK/AAB builds, product audit, and parity ledger verification.
- [ ] Deploy an immutable release, migrate safely, clear caches, and restart affected workers.
- [ ] Publish the APK and release manifest.
- [ ] Smoke test public, customer, administrator, shared-report, payment-safe, API, `/public` redirect, security-block, and download routes.
- [ ] Download the production APK, compare SHA-256, install it, and exercise production API journeys.
- [ ] Record evidence for every explicit requirement and keep the goal incomplete if any evidence is missing or indirect.
- [ ] Roll back immediately on a critical failure; otherwise mark the release accepted.

## Global completion commands

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never
vendor\bin\pint --test
npm run build
npm run test:browser
php artisan product:audit
Push-Location mobile
flutter analyze --no-pub
flutter test --no-pub
flutter build apk --release --dart-define=API_BASE_URL=https://khaledsaad.net/api
flutter build appbundle --release --dart-define=API_BASE_URL=https://khaledsaad.net/api
Pop-Location
```

Expected: every command exits with code 0, every parity record is verified, and the production download checksum equals the locally generated release checksum.
