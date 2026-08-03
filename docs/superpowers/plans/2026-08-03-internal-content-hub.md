# Internal Content Hub Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace LinkedIn-backed homepage cards with a professional internal publishing system for articles, lessons, lectures, and email-gated courses.

**Architecture:** A single `Content` aggregate owns publishable metadata and editor output. Courses compose existing lesson and lecture records through ordered sections. A shared access service enforces public versus email-subscriber access consistently for Blade and API, while a server sanitizer owns trusted HTML.

**Tech Stack:** Laravel 13, Blade, MySQL/SQLite tests, Vite, vanilla JavaScript, Tiptap open-source packages, Laravel public storage.

---

### Task 1: Content domain and lifecycle

**Files:**
- Create: `database/migrations/2026_08_03_000001_create_contents_table.php`
- Create: `database/migrations/2026_08_03_000002_create_course_sections_table.php`
- Create: `database/migrations/2026_08_03_000003_create_course_section_items_table.php`
- Create: `app/Models/Content.php`
- Create: `app/Models/CourseSection.php`
- Create: `tests/Feature/ContentModelTest.php`

- [ ] Write a failing model test proving new content defaults to `article`, `draft`, and `public`, published scope excludes scheduled/archived records, and slugs bind routes.
- [ ] Run `php artisan test --compact tests/Feature/ContentModelTest.php`; expect failure because tables and models do not exist.
- [ ] Add the three migrations with indexed `slug`, `type`, `status`, `access_level`, `published_at`; add unique ordered section/item constraints and foreign keys.
- [ ] Implement constants, casts, fillable fields, `published()`, `isPublished()`, `isSubscriberOnly()`, section/item relations, and route key binding.
- [ ] Re-run the test and expect pass.

### Task 2: Subscriber gate and safe rendering

**Files:**
- Create: `database/migrations/2026_08_03_000004_create_content_subscribers_table.php`
- Create: `app/Models/ContentSubscriber.php`
- Create: `app/Services/Content/ContentSubscriptionService.php`
- Create: `app/Services/Content/ContentAccessService.php`
- Create: `app/Services/Content/ContentHtmlSanitizer.php`
- Create: `tests/Unit/ContentHtmlSanitizerTest.php`
- Create: `tests/Feature/ContentAccessTest.php`

- [ ] Write failing tests proving scripts, event handlers, unsafe URLs, and unknown tags are removed while semantic editor markup survives.
- [ ] Run the sanitizer test and verify failure because the service is absent.
- [ ] Implement a DOMDocument allowlist sanitizer for headings, paragraphs, lists, links, images, tables, code, task lists, and YouTube no-cookie iframes.
- [ ] Write failing access tests proving public content opens without a subscriber, gated bodies remain absent, and a valid token unlocks them.
- [ ] Add subscribers with unique normalized email, status, consent timestamp, and SHA-256 token hash; implement random token issue/rotation and constant-time lookup.
- [ ] Implement one access service used by web sessions and the `X-Content-Token` header.
- [ ] Run both test files and expect pass.

### Task 3: Admin content CRUD

**Files:**
- Create: `app/Http/Requests/Admin/ContentRequest.php`
- Create: `app/Http/Controllers/Admin/AdminContentController.php`
- Create: `resources/views/admin/content/index.blade.php`
- Create: `resources/views/admin/content/form.blade.php`
- Create: `resources/views/admin/content/_fields.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/panel-nav.blade.php`
- Create: `tests/Feature/AdminContentManagementTest.php`

- [ ] Write failing tests for admin-only list/create/update/archive, unique slug validation, free default, subscriber override, sanitization, scheduling, and non-admin denial.
- [ ] Run the test and verify route-not-found failures.
- [ ] Add an admin resource and archive/restore actions behind the existing `auth,admin` group.
- [ ] Implement validated persistence through `ContentRequest`; sanitize HTML server-side and never trust the hidden HTML input directly.
- [ ] Build Arabic RTL list/form views with type, status, access, publish date, SEO, media, and preview controls.
- [ ] Add ????????? to the unified admin navigation and re-run the test.

### Task 4: Local advanced editor and media

**Files:**
- Modify: `package.json`
- Modify: `package-lock.json`
- Create: `resources/js/content-editor.js`
- Modify: `resources/js/app.js`
- Create: `resources/css/content-editor.css`
- Modify: `resources/css/app.css`
- Create: `app/Http/Controllers/Admin/AdminContentMediaController.php`
- Create: `app/Http/Requests/Admin/ContentMediaRequest.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/AdminContentMediaTest.php`

- [ ] Write failing upload tests for valid image/document media, randomized public paths, admin-only access, size checks, and SVG/executable rejection.
- [ ] Run the test and verify missing route/controller failures.
- [ ] Install local Tiptap core, ProseMirror, StarterKit, image, table, text-align, highlight, task-list, task-item, YouTube, placeholder, and character-count packages with npm.
- [ ] Implement the media upload endpoint and JSON response consumed by the editor.
- [ ] Build the editor toolbar, RTL alignment, image upload, tables, tasks, YouTube, character/word counts, fullscreen, JSON/HTML hidden fields, and preview.
- [ ] Import editor JS/CSS only through Vite and verify no CDN reference exists.
- [ ] Run the media tests and `npm run build`; expect both to pass.

### Task 5: Course curriculum

**Files:**
- Create: `app/Http/Controllers/Admin/AdminCourseCurriculumController.php`
- Create: `app/Http/Requests/Admin/CourseSectionRequest.php`
- Create: `resources/views/admin/content/curriculum.blade.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/AdminCourseCurriculumTest.php`

- [ ] Write failing tests proving only courses accept sections, only lessons/lectures can be attached, positions remain unique, and delete/reorder is admin-only.
- [ ] Run the test and verify missing route failures.
- [ ] Add section create/update/delete and item attach/detach/reorder endpoints using transactions.
- [ ] Render a curriculum builder with section cards, eligible item selector, and explicit numeric ordering that works without JavaScript.
- [ ] Re-run the course curriculum test and expect pass.

### Task 6: Public library and email unlock

**Files:**
- Create: `app/Http/Controllers/Site/ContentLibraryController.php`
- Create: `app/Http/Controllers/Site/ContentSubscriptionController.php`
- Create: `resources/views/site/content/index.blade.php`
- Create: `resources/views/site/content/show.blade.php`
- Create: `resources/views/site/content/_gate.blade.php`
- Create: `resources/views/site/content/_curriculum.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/site-header.blade.php`
- Modify: `resources/views/partials/site-footer.blade.php`
- Create: `tests/Feature/PublicContentLibraryTest.php`

- [ ] Write failing tests for published-only listings, type filtering, 404 for drafts, locked body absence, email validation/consent, session unlock, and course curriculum.
- [ ] Run the test and verify route failures.
- [ ] Add `/blog`, `/blog/{content:slug}`, and throttled subscription routes.
- [ ] Render responsive Arabic cards, article typography, course curriculum, locked previews, and a privacy-consent email form.
- [ ] Point public navigation to the real library and re-run the test.

### Task 7: Homepage, API, sitemap, and legacy removal

**Files:**
- Modify: `app/Http/Controllers/Site/HomeController.php`
- Modify: `resources/views/home.blade.php`
- Create: `app/Http/Controllers/Api/V1/PublicContentLibraryController.php`
- Create: `app/Http/Controllers/Api/V1/PublicContentSubscriptionController.php`
- Modify: `app/Http/Controllers/Api/V1/PublicContentController.php`
- Modify: `routes/api.php`
- Modify: `routes/web.php`
- Modify: `config/brand.php`
- Create: `tests/Feature/PublicContentApiTest.php`
- Modify: `tests/Unit/BrandProfileConfigurationTest.php`

- [ ] Write failing tests for latest homepage items, API pagination/type filters, gated response redaction, token unlock, and sitemap inclusion.
- [ ] Run the tests and verify failures against the static `brand.knowledge` implementation.
- [ ] Query the latest three published records in HomeController and render internal links with an honest empty state.
- [ ] Add API list/show/subscribe resources using the same access and subscription services as web.
- [ ] Merge published content URLs into the sitemap and invalidate its cache on content changes.
- [ ] Remove `knowledge` from public bootstrap and brand config consumers without touching other brand data.
- [ ] Re-run both feature tests and the adjusted brand unit test.

### Task 8: Final quality gate

**Files:**
- Modify: `docs/superpowers/specs/2026-08-03-internal-content-hub-design.md` only if implementation discoveries require an explicit clarification.
- Review: all files changed on `codex/internal-content-hub`.

- [ ] Run `php artisan test --compact tests/Feature/ContentModelTest.php tests/Unit/ContentHtmlSanitizerTest.php tests/Feature/ContentAccessTest.php tests/Feature/AdminContentManagementTest.php tests/Feature/AdminContentMediaTest.php tests/Feature/AdminCourseCurriculumTest.php tests/Feature/PublicContentLibraryTest.php tests/Feature/PublicContentApiTest.php tests/Unit/BrandProfileConfigurationTest.php`.
- [ ] Run `vendor/bin/pint --test`.
- [ ] Run `npm run build`.
- [ ] Run `php artisan route:list --path=blog` and `php artisan route:list --path=admin/content` and inspect methods/middleware.
- [ ] Inspect `git diff --check`, scan for `linkedin` in the homepage knowledge section and for CDN imports in editor assets.
- [ ] Request an independent code review, fix Critical/Important findings, and repeat the focused test/build commands.
