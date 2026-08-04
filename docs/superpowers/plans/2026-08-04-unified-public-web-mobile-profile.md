# Unified Public Web, Mobile, and Profile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a responsive public web experience, full professional profile pages, and native Flutter access to the same information and content.

**Architecture:** Laravel owns the structured brand/profile/content data and exposes it through existing v1 public APIs. Blade renders crawlable standalone pages and a PDF, while Flutter adds a native public navigation shell that consumes those APIs without duplicating biography data.

**Tech Stack:** Laravel 13, Blade, CSS, Dompdf, PHPUnit, Flutter/Dart, flutter_html, Flutter widget tests.

---

### Task 1: Lock the approved public information design

**Files:**
- Create: `docs/superpowers/specs/2026-08-04-unified-public-web-mobile-profile-design.md`
- Create: `docs/superpowers/plans/2026-08-04-unified-public-web-mobile-profile.md`

- [ ] **Step 1: Save the approved architecture and acceptance criteria**

Record the approved routes, single-source rule, mobile navigation, privacy limits, and responsive acceptance criteria in the design document.

- [ ] **Step 2: Review for missing scope and placeholders**

Run:

```powershell
rg -n "TO_BE_DECIDED|PLACEHOLDER" docs/superpowers/specs/2026-08-04-unified-public-web-mobile-profile-design.md docs/superpowers/plans/2026-08-04-unified-public-web-mobile-profile.md
```

Expected: no output.

### Task 2: Fix the mobile reading regression

**Files:**
- Modify: `resources/css/content-library.css`
- Modify: `tests/Unit/LearningMagazineStyleTest.php`

- [ ] **Step 1: Write a failing cascade regression test**

Add a test that finds the final `@media (max-width: 1050px)` learning override and asserts it contains:

```php
$this->assertStringContainsString(
    '.content-page--learning .content-reading-grid',
    $mobileRules,
);
$this->assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $mobileRules);
```

- [ ] **Step 2: Run the test and confirm RED**

Run: `php artisan test tests/Unit/LearningMagazineStyleTest.php`

Expected: FAIL because the late learning-specific rule still restores two columns.

- [ ] **Step 3: Add the minimal late mobile override and RTL tab containment**

Append mobile rules after the learning desktop rules:

```css
@media (max-width: 1050px) {
    .content-page--learning .content-reading-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .content-type-tabs {
        grid-template-columns: repeat(5, minmax(8.5rem, 1fr));
        overscroll-behavior-inline: contain;
        scroll-padding-inline: 1rem;
    }
}
```

- [ ] **Step 4: Run the focused test and confirm GREEN**

Run: `php artisan test tests/Unit/LearningMagazineStyleTest.php`

Expected: PASS.

### Task 3: Add complete professional profile data

**Files:**
- Modify: `config/brand.php`
- Modify: `tests/Unit/BrandProfileConfigurationTest.php`

- [ ] **Step 1: Write failing assertions for the complete profile**

Assert the current title, expanded about text, responsibility arrays, professional services, and knowledge topics:

```php
$this->assertSame('مدير التسويق', $brand['experience'][0]['role']);
$this->assertNotEmpty($brand['professional_services']);
$this->assertNotEmpty($brand['knowledge']);
$this->assertArrayHasKey('responsibilities', $brand['experience'][2]);
```

- [ ] **Step 2: Run the test and confirm RED**

Run: `php artisan test tests/Unit/BrandProfileConfigurationTest.php`

Expected: FAIL because the structured fields are not present yet.

- [ ] **Step 3: Add only verified data**

Extend `config/brand.php` with the verified professional headline, expanded biography, responsibilities from `docs/content/professional-profile.md`, professional services, and knowledge topics. Do not add follower counts, private details, or unsupported campaign results.

- [ ] **Step 4: Run the focused test and confirm GREEN**

Run: `php artisan test tests/Unit/BrandProfileConfigurationTest.php`

Expected: PASS.

### Task 4: Build standalone crawlable web pages and CV PDF

**Files:**
- Create: `app/Http/Controllers/Site/PublicPageController.php`
- Create: `app/Http/Controllers/Site/ProfilePdfController.php`
- Create: `resources/views/site/pages/show.blade.php`
- Create: `resources/views/site/pages/profile.blade.php`
- Create: `resources/views/site/pages/profile-pdf.blade.php`
- Create: `resources/css/public-pages.css`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/public.blade.php`
- Modify: `resources/views/home.blade.php`
- Create: `tests/Feature/PublicProfessionalPagesTest.php`

- [ ] **Step 1: Write failing route/content/PDF tests**

Create tests asserting named routes, 200 responses, the profile timeline, verified contact links, JSON-LD, PDF content type, and sitemap inclusion.

- [ ] **Step 2: Run the tests and confirm RED**

Run: `php artisan test tests/Feature/PublicProfessionalPagesTest.php`

Expected: FAIL because routes and controllers do not exist.

- [ ] **Step 3: Implement named pages from `config('brand')`**

Create a route map for `profile`, `services`, `methodology`, `principles`, `knowledge`, and `faq`. Use dedicated profile markup and the shared page template for the supporting pages. Add page links to the public navigation, homepage sections, footer, and sitemap.

- [ ] **Step 4: Implement PDF from the same data**

Use `Barryvdh\DomPDF\Facade\Pdf`:

```php
return Pdf::loadView('site.pages.profile-pdf', ['brand' => config('brand')])
    ->setPaper('a4')
    ->download('Khaled-Saad-CV-ar.pdf');
```

- [ ] **Step 5: Run focused tests and confirm GREEN**

Run: `php artisan test tests/Feature/PublicProfessionalPagesTest.php tests/Feature/PublicHomePageTest.php`

Expected: PASS.

### Task 5: Extend the public API for native parity

**Files:**
- Modify: `app/Http/Controllers/Api/V1/PublicContentController.php`
- Modify: `tests/Feature/PublicApiParityTest.php`

- [ ] **Step 1: Write failing API assertions**

Assert bootstrap exposes `professional_headline`, `professional_services`, `principles`, and `knowledge`, plus web and PDF profile links.

- [ ] **Step 2: Run and confirm RED**

Run: `php artisan test tests/Feature/PublicApiParityTest.php --filter=public_bootstrap`

Expected: FAIL for the missing keys or links.

- [ ] **Step 3: Extend the allowlist and links**

Add verified keys to `BRAND_KEYS` and return named `profile`, `profile_pdf`, `services`, `methodology`, `knowledge`, and `faq` URLs.

- [ ] **Step 4: Run and confirm GREEN**

Run: `php artisan test tests/Feature/PublicApiParityTest.php --filter=public_bootstrap`

Expected: PASS.

### Task 6: Add native Flutter models, repository calls, and public navigation

**Files:**
- Modify: `mobile/pubspec.yaml`
- Modify: `mobile/lib/core/api/platform_repository.dart`
- Create: `mobile/lib/features/public/public_content_models.dart`
- Create: `mobile/lib/features/public/public_content_screen.dart`
- Create: `mobile/lib/features/public/public_profile_screen.dart`
- Create: `mobile/lib/features/public/public_info_screen.dart`
- Create: `mobile/lib/features/public/public_shell.dart`
- Modify: `mobile/lib/features/public/public_home_screen.dart`
- Modify: `mobile/lib/main.dart`
- Create: `mobile/test/features/public_experience_test.dart`

- [ ] **Step 1: Write failing model and widget tests**

Test JSON parsing, repository paths, five native destinations, CV experience rendering, and content card rendering from injected data.

- [ ] **Step 2: Run tests and confirm RED**

Run: `flutter test test/features/public_experience_test.dart`

Expected: FAIL because public content models and shell do not exist.

- [ ] **Step 3: Implement models and repository calls**

Add:

```dart
Future<Map<String, dynamic>> publicContent({String? type, int page = 1});
Future<Map<String, dynamic>> publicContentDetail(int id);
```

Map summaries and details to immutable Dart models.

- [ ] **Step 4: Implement the native public shell**

Use `NavigationBar` for الرئيسية، المعرفة، الأدوات، السيرة، المزيد. Reuse bootstrap data across destinations and keep login/register callbacks available.

- [ ] **Step 5: Render article HTML natively**

Add `flutter_html` and render `body_html` with RTL typography, cover image, metadata, locked-state message, and retry behavior.

- [ ] **Step 6: Run tests and confirm GREEN**

Run: `flutter test test/features/public_experience_test.dart`

Expected: PASS.

### Task 7: Verify, build, publish, and smoke test

**Files:**
- Modify: `mobile/pubspec.yaml` version/build
- Build artifact: configured Android APK path

- [ ] **Step 1: Run Laravel verification**

Run: `php artisan test tests/Unit/LearningMagazineStyleTest.php tests/Unit/BrandProfileConfigurationTest.php tests/Feature/PublicProfessionalPagesTest.php tests/Feature/PublicApiParityTest.php tests/Feature/PublicHomePageTest.php tests/Feature/PublicContentExperienceTest.php`

Expected: all PASS.

- [ ] **Step 2: Run asset build**

Run: `npm run build`

Expected: exit 0.

- [ ] **Step 3: Run Flutter verification and Android build**

Run: `flutter pub get`, `flutter analyze`, `flutter test`, then `flutter build apk --release`.

Expected: exit 0 for all commands and a release APK.

- [ ] **Step 4: Commit and push intentionally**

Stage only files in this plan, commit with a scoped message, and push `codex/learning-magazine-redesign`.

- [ ] **Step 5: Deploy using the repository production workflow**

Inspect the existing deployment script/documentation, deploy the tested commit without changing `APP_KEY`, clear application caches, and publish the verified APK to the configured download path.

- [ ] **Step 6: Verify live behavior**

Check 200 responses for the homepage, profile, PDF, supporting pages, public bootstrap, content index, first lesson, sitemap, and Android download. Capture mobile screenshots at representative widths and confirm the reading grid remains one column.
