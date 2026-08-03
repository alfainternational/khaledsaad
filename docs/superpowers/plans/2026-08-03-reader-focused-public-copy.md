# Reader-Focused Public Copy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace reader-irrelevant implementation copy across the public homepage and content library with practical benefit-led language.

**Architecture:** Keep copy in the existing Blade views and protect the wording at rendered-response level. The audit is contextual: legal, security, status, and authenticated operational copy is excluded.

**Tech Stack:** Laravel 13, Blade, PHPUnit.

---

### Task 1: Public-copy regression test

**Files:**
- Modify: `tests/Feature/PublicHomePageTest.php`
- Modify: `tests/Feature/PublicContentLibraryTest.php`

- [ ] Add homepage assertions for `محتوى يحول المعرفة إلى خطوات` and against `من داخل المنصة`, `أنشره هنا مباشرة`, and `فور نشره من لوحة الإدارة`.
- [ ] Add library assertions for `محتوى يساعدك على الفهم والتطبيق` and against `LinkedIn أو أي منصة خارجية` and `فور نشره من لوحة الإدارة`.
- [ ] Run `php artisan test --compact tests/Feature/PublicHomePageTest.php tests/Feature/PublicContentLibraryTest.php` and verify failure on the missing benefit-led copy.

### Task 2: Benefit-led public copy

**Files:**
- Modify: `resources/views/home.blade.php`
- Modify: `resources/views/site/content/index.blade.php`

- [ ] Replace the homepage content title with `محتوى يحول المعرفة إلى خطوات`.
- [ ] Replace its description with copy explaining that articles, lessons, lectures, and courses help the reader understand the problem and apply a clear next step.
- [ ] Replace the homepage empty state with an honest invitation to begin the free diagnosis.
- [ ] Replace the library hero with `محتوى يساعدك على الفهم والتطبيق` and a description focused on clearer decisions and practical execution.
- [ ] Replace the library empty state with the same useful next action without mentioning publishing or administration.
- [ ] Re-run both feature test files and verify all assertions pass.

### Task 3: Verification and deployment

**Files:**
- Verify and deploy the two Blade files and tests above.

- [ ] Search public view files for `من داخل المنصة`, `أنشره هنا مباشرة`, `LinkedIn أو أي منصة خارجية`, and `فور نشره من لوحة الإدارة`; expect zero matches.
- [ ] Run `php artisan test --compact tests/Feature/PublicHomePageTest.php tests/Feature/PublicContentLibraryTest.php` and `git diff --check`.
- [ ] Commit, push `codex/internal-content-hub`, deploy the two Blade files, and clear production view cache.
- [ ] Fetch the live homepage and library and verify the new copy is present and the rejected phrases are absent.
