# Content Attachments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add multiple uploaded files and titled external links to every internal content item, with protected public downloads.

**Architecture:** Store ordered resource records in a normalized `content_resources` table linked to `contents` and the existing `content_media` library. Synchronize validated resources from the content form inside database transactions, then render them only inside the existing unlocked content branch.

**Tech Stack:** Laravel 13, Eloquent, Blade, vanilla JavaScript, Vite, PHPUnit.

---

### Task 1: Resource domain and persistence

**Files:**
- Create: `database/migrations/2026_08_03_000006_create_content_resources_table.php`
- Create: `app/Models/ContentResource.php`
- Modify: `app/Models/Content.php`
- Test: `tests/Feature/ContentResourceTest.php`

- [ ] Write a failing test that creates a content item with ordered file and link resources and asserts the Eloquent relationship returns them by position.
- [ ] Run `php artisan test --compact tests/Feature/ContentResourceTest.php` and verify it fails because the table and model do not exist.
- [ ] Add the migration, model, relationships, casts, and `file` / `link` type constants.
- [ ] Re-run the resource test and verify it passes.

### Task 2: Admin validation and synchronization

**Files:**
- Modify: `app/Http/Requests/Admin/ContentRequest.php`
- Modify: `app/Http/Controllers/Admin/AdminContentController.php`
- Modify: `app/Http/Requests/Admin/ContentMediaRequest.php`
- Modify: `app/Http/Controllers/Admin/AdminContentMediaController.php`
- Test: `tests/Feature/AdminContentManagementTest.php`
- Test: `tests/Feature/AdminContentMediaTest.php`

- [ ] Write failing tests proving valid file/link resources synchronize on create and update, malformed entries fail validation, office/archive materials upload, and referenced media cannot be deleted.
- [ ] Run the two feature test files and verify the new assertions fail for the missing behavior.
- [ ] Decode the hidden resource JSON during request preparation, validate type-specific fields, remove it from the content payload, and synchronize rows inside a transaction after content persistence.
- [ ] Extend the media allowlist with DOCX, PPTX, XLSX, ZIP, TXT, CSV, and audio formats while keeping executable and SVG rejection.
- [ ] Extend deletion protection to attached media and return original file metadata from the upload endpoint.
- [ ] Re-run both feature test files and verify they pass.

### Task 3: Admin editor experience

**Files:**
- Modify: `resources/views/admin/content/form.blade.php`
- Create: `resources/js/content-resources.js`
- Modify: `resources/js/app.js`
- Modify: `resources/css/content-editor.css`
- Test: `tests/Feature/AdminContentManagementTest.php`

- [ ] Write a failing view test asserting the editor contains the resources component, its upload endpoint, and serialized existing records.
- [ ] Run that test and verify it fails because the component is absent.
- [ ] Add file upload and external-link controls, an accessible ordered list, remove controls, progress/error feedback, and a hidden JSON field.
- [ ] Import the local component through Vite and style it responsively.
- [ ] Re-run the view test and `npm run build`; verify both pass.

### Task 4: Protected public display and download

**Files:**
- Modify: `app/Http/Controllers/Site/ContentLibraryController.php`
- Modify: `app/Http/Controllers/Site/ContentMediaController.php`
- Modify: `resources/views/site/content/show.blade.php`
- Modify: `resources/css/content-library.css`
- Test: `tests/Feature/PublicContentLibraryTest.php`

- [ ] Write failing tests proving resources render for unlocked content, stay hidden behind the email gate, and attached file downloads follow the parent content access rule.
- [ ] Run the public library test and verify failures for the absent resource rendering and reference lookup.
- [ ] Eager-load resources, render the ordered section inside the unlocked branch, and recognize resource references in the media access controller.
- [ ] Send resource files with their original filename and safe MIME headers.
- [ ] Re-run the public library test and verify it passes.

### Task 5: Full verification and delivery

**Files:**
- Verify all files above.

- [ ] Run `php artisan test --compact tests/Feature/ContentResourceTest.php tests/Feature/AdminContentManagementTest.php tests/Feature/AdminContentMediaTest.php tests/Feature/PublicContentLibraryTest.php` and verify zero failures.
- [ ] Run `vendor/bin/pint --test` on changed PHP files and `git diff --check`.
- [ ] Run `npm run build` and verify exit code 0.
- [ ] Run the production migration and deploy changed application files plus `public/build` through `deploy/cpanel-push.sh`.
- [ ] Verify the live editor contains the materials component and the live asset manifest references the new build.
