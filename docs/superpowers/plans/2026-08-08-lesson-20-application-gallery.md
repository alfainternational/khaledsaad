# Lesson 20 Application Gallery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn lesson 20 into the published entry point for all course lessons and applications while preserving the original workshop as a collapsed reference.

**Architecture:** A focused `MarketingCourseGalleryPresenter` maps the existing catalog and the authenticated learner's workspace run into a read-only gallery model. `ContentLibraryController` requests that model only for lesson 20, and a dedicated Blade partial renders it above the original body. Existing protected exercise routes remain the only way to start or resume work, so guest intended redirects, entitlement gates, attempts, scores, and reviews keep one source of truth.

**Tech Stack:** Laravel 13, Blade, Eloquent, existing billing entitlements and marketing learning models, PHPUnit, Vite CSS.

---

### Task 1: Lock the page contract with feature tests

**Files:**
- Modify: `tests/Feature/ProjectlessMarketingLearningTest.php`

- [ ] Add a guest test that imports the course, opens lesson 20, sees «معرض الدروس والتطبيقات», 20 lesson cards, direct protected exercise URLs, and the collapsed «الورشة التطبيقية الكاملة» reference.
- [ ] Add an entitled-user test that creates completed and draft attempts, opens lesson 20, and sees completed/remaining totals, the score, and result/resume links.
- [ ] Add an unentitled-user test that disables `learning.marketing`, opens lesson 20, sees the locked state and billing link, and does not require a project.
- [ ] Run `php artisan test tests/Feature/ProjectlessMarketingLearningTest.php --filter=gallery` and confirm the new tests fail because the gallery does not exist.

### Task 2: Build one gallery view model from existing sources

**Files:**
- Create: `app/Modules/Learning/MarketingCourseGalleryPresenter.php`
- Modify: `app/Http/Controllers/Site/ContentLibraryController.php`

- [ ] Implement `present(?User $user): array` using `MarketingCourseCatalog`, `Entitlements`, `MarketingLearningRun`, and existing attempt status constants.
- [ ] Return `lesson_count`, `exercise_count`, `completed_count`, `remaining_count`, `average_score`, `entitled`, and mapped lessons/exercises with status labels, scores, protected exercise/result URLs, and source URLs.
- [ ] For guests, return catalog data without creating a run; direct exercise URLs rely on auth middleware to preserve the intended destination.
- [ ] For entitled users, load or start the workspace-owned projectless run and its attempts; for unentitled users, do not create a run and use the billing URL.
- [ ] In `ContentLibraryController::show`, build the gallery only when `source_key === 'marketing-course-20'` and pass it to the content view.
- [ ] Run the gallery tests and confirm the data contract passes.

### Task 3: Render the dedicated lesson gallery and preserve the workshop

**Files:**
- Create: `resources/views/site/content/_marketing-course-gallery.blade.php`
- Modify: `resources/views/site/content/show.blade.php`
- Modify: `resources/css/content-library.css`

- [ ] Render a summary header with Latin-digit totals and a clear login or billing message when applicable.
- [ ] Render 20 lesson cards in RTL order, each containing applications, purpose, deliverable, duration, status, score, direct action, and «اقرأ الدرس».
- [ ] Wrap the original lesson 20 `body_html` in a closed `<details>` titled «الورشة التطبيقية الكاملة» and explain that answers are saved through the applications above.
- [ ] Add fluid desktop and mobile gallery styles using the global 1cm gutter, no fixed page cap, visible focus states, and no horizontal overflow.
- [ ] Run the gallery tests and responsive layout tests.

### Task 4: Publish the revised content identity

**Files:**
- Modify: `database/data/content/marketing-course/manifest.php`
- Modify: `database/data/content/marketing-course/manifest.json`
- Modify: `database/data/content/marketing-course/lessons/20.php`
- Modify: `database/data/learning/marketing-course.php`

- [ ] Change the public title to «معرض الدروس والتطبيقات» while retaining the existing slug and source key.
- [ ] Keep lesson 20's long `body_html`, outline, cover, and source text intact.
- [ ] Keep all lesson 20 application definitions in the catalog and update its short title to match the gallery identity.
- [ ] Run `php artisan content:import-marketing-course --publish --force` locally and confirm lesson 20 is published.

### Task 5: Verify, build, and deploy

**Files:**
- Runtime files from Tasks 2-4
- Built assets: `public/build`

- [ ] Run focused gallery, learning journey, public content, entitlement, and responsive tests.
- [ ] Run Pint on changed PHP files and `php deploy/check-class-case.php`.
- [ ] Run `npm run build`.
- [ ] Inspect lesson 20 in a desktop and mobile browser, checking RTL, English digits, direct actions, collapsed workshop, and no horizontal overflow.
- [ ] Back up production database, deploy the scoped runtime files and build, re-import the published marketing course, clear caches, and restart the queue.
- [ ] Verify the live lesson URL returns 200, displays the build hash and gallery title, authenticated routes remain protected, and the page is not a draft.
