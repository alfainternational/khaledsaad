# Question Input Affordance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every user-facing question input visibly interactive and present its reason as a distinct visual explanation without the words «لماذا نسأل؟».

**Architecture:** Reuse the existing `.field` system and add two semantic hooks in `resources/css/workspace.css`: `.question-control` for answer controls and `.question-reason` for explanations. Apply those hooks in every shared or repeated question renderer while preserving names, types, options, validation, and stored values.

**Tech Stack:** Laravel Blade, CSS, Vite, PHPUnit.

---

### Task 1: Bring the approved question copy onto `main`

**Files:**
- Modify: the already approved question catalog, renderers, and copy files from commits `45126be`, `e8825d2`, `385aa68`, and `0ab8392`.

- [ ] **Step 1: Cherry-pick the approved commits**

Run: `git cherry-pick 45126be e8825d2 385aa68 0ab8392`

Expected: the existing question rewrite lands on `main` without changing the untracked deployment packages.

- [ ] **Step 2: Run the existing copy contract**

Run: `php artisan test --compact tests/Feature/UserFacingQuestionCopyTest.php`

Expected: all question labels and reasons satisfy the approved copy contract.

### Task 2: Add the failing visual contract

**Files:**
- Modify: `tests/Feature/UserFacingQuestionCopyTest.php`

- [ ] **Step 1: Add assertions for the shared styles and markup**

Assert that `resources/css/workspace.css` contains `.question-control` and `.question-reason`, that question renderers use these classes, and that customer renderers do not contain visible `لماذا نسأل؟` headings.

- [ ] **Step 2: Run the test and verify RED**

Run: `php artisan test --compact tests/Feature/UserFacingQuestionCopyTest.php`

Expected: FAIL because the shared classes do not exist yet and the consultation renderer still prints the heading.

### Task 3: Implement the shared question affordance

**Files:**
- Modify: `resources/css/workspace.css`
- Modify: `resources/views/app/consultations/show.blade.php`
- Modify: `resources/views/app/consultations/_answer-field.blade.php`
- Modify: `resources/views/app/runs/partials/field.blade.php`
- Modify: `resources/views/app/agency-reports/index.blade.php`
- Modify: `resources/views/app/projects/create.blade.php`
- Modify: `resources/views/app/projects/edit.blade.php`

- [ ] **Step 1: Add the central styles**

Add a visible border/background/minimum height/focus state for `.question-control`, and a non-collapsible explanatory callout for `.question-reason` with an icon supplied by CSS and `aria-label="سبب طرح السؤال"` in markup.

- [ ] **Step 2: Apply the styles to every renderer**

Add `question-control` to text, textarea, number, URL, select, radio, boolean, and multiselect containers. Replace visible why headings/details with a plain `.question-reason` paragraph after the control.

- [ ] **Step 3: Run the contract and focused feature tests**

Run: `php artisan test --compact tests/Feature/UserFacingQuestionCopyTest.php tests/Feature/ConsultationApiTest.php tests/Feature/WebAppJourneyTest.php`

Expected: PASS.

### Task 4: Verify and publish

**Files:**
- Build: `public/build/`
- Deploy: only changed runtime files and built assets.

- [ ] **Step 1: Format and verify**

Run: `vendor/bin/pint --dirty && npm run build && php artisan test --compact tests/Feature/UserFacingQuestionCopyTest.php tests/Feature/ConsultationApiTest.php tests/Feature/WebAppJourneyTest.php && git diff --check`

Expected: all commands exit successfully.

- [ ] **Step 2: Commit on `main`**

Commit the tests and implementation with `fix: clarify all question inputs and reasons`.

- [ ] **Step 3: Deploy with rollback protection**

Create a timestamped remote backup, upload the changed Blade/CSS build files, clear Laravel caches, and restart PHP opcache.

- [ ] **Step 4: Verify production**

Confirm the consultation and tool question pages load, the new CSS asset is served, the input controls are visibly styled, the visible phrase «لماذا نسأل؟» is absent, and the reason callout is present.
