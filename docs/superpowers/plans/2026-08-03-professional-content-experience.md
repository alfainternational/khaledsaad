# Professional Content Experience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress-familiar public content experience, icon-first local editor, and admin-managed content categories without losing existing content.

**Architecture:** Add a nullable category relation beside the existing content type, expose it through admin CRUD and public query filters, then reshape the two public Blade views around featured cards and readable article layouts. Keep Tiptap local and enhance only its toolbar presentation and grouping.

**Tech Stack:** Laravel 13, Eloquent, Blade, Tiptap 3, vanilla JavaScript, CSS, Vite, PHPUnit.

---

### Task 1: Content category domain

**Files:**
- Create: `app/Models/ContentCategory.php`
- Create: `database/migrations/2026_08_03_000007_create_content_categories_table.php`
- Modify: `app/Models/Content.php`
- Test: `tests/Feature/ContentCategoryTest.php`

- [ ] Write failing tests for category defaults, the category/content relation, and nullable compatibility.
- [ ] Run `php artisan test --compact tests/Feature/ContentCategoryTest.php` and confirm it fails because the model/table do not exist.
- [ ] Add the table, model scopes, fillable fields, casts, and Eloquent relations.
- [ ] Rerun the test and commit the passing domain change.

### Task 2: Admin category management and assignment

**Files:**
- Create: `app/Http/Controllers/Admin/AdminContentCategoryController.php`
- Create: `app/Http/Requests/Admin/ContentCategoryRequest.php`
- Create: `resources/views/admin/content/categories/index.blade.php`
- Create: `resources/views/admin/content/categories/form.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Requests/Admin/ContentRequest.php`
- Modify: `app/Http/Controllers/Admin/AdminContentController.php`
- Modify: `resources/views/admin/content/form.blade.php`
- Create: `resources/js/content-cover.js`
- Modify: `resources/js/app.js`
- Modify: `resources/views/admin/content/index.blade.php`
- Test: `tests/Feature/AdminContentCategoryTest.php`

- [ ] Write failing admin tests for CRUD, assignment, filtering, validation, and protected deletion.
- [ ] Run the focused test and confirm category routes/fields are missing.
- [ ] Implement admin routes, request validation, controller operations, views, and content assignment/filtering.
- [ ] Add a main-image uploader with preview, replace, and remove controls backed by the existing secure media endpoint.
- [ ] Rerun the focused tests and commit.

### Task 3: Public discovery and reading experience

**Files:**
- Modify: `app/Http/Controllers/Site/ContentLibraryController.php`
- Modify: `resources/views/site/content/index.blade.php`
- Modify: `resources/views/site/content/show.blade.php`
- Modify: `resources/css/content-library.css`
- Test: `tests/Feature/PublicContentExperienceTest.php`

- [ ] Write failing tests for search, category filter, counts, featured layout, category metadata, and related content.
- [ ] Run the focused test and confirm the new UI/data is absent.
- [ ] Add validated query filters, counts, active categories, featured state, and related materials.
- [ ] Build the responsive library and reading layout with accessible labels and fallback visuals.
- [ ] Rerun public content tests and commit.

### Task 4: Icon-first editor toolbar

**Files:**
- Modify: `resources/views/admin/content/form.blade.php`
- Modify: `resources/js/content-editor.js`
- Modify: `resources/css/content-editor.css`
- Test: `tests/Feature/AdminContentManagementTest.php`

- [ ] Add a failing view contract test for grouped toolbar semantics, instructions, and icon-first controls.
- [ ] Run the focused test and confirm the contract is missing.
- [ ] Define grouped actions and inline SVG icons, preserving Arabic tooltips and accessible labels.
- [ ] Style sticky groups, active states, mobile horizontal scrolling, and clearer editor canvas/status.
- [ ] Rerun the admin content test and commit.

### Task 5: Verification and deployment

**Files:**
- Modify generated assets under `public/build` through Vite output only.

- [ ] Run Pint on changed PHP files.
- [ ] Run all content feature/unit tests and confirm zero failures.
- [ ] Run `npm run build` and `git diff --check`.
- [ ] Commit and push `codex/internal-content-hub`.
- [ ] Deploy the migration, PHP/Blade/CSS/JS sources, and built manifest/assets with the existing cPanel script.
- [ ] Run migrations and cache clearing, then verify `/blog`, a content page, admin categories, and editor assets on production.
