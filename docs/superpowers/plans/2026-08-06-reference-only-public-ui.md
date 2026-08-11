# Reference-Only Public UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the public marketing interface from the supplied reference image, preserving only RTL and Latin digits 0-9.

**Architecture:** Keep Laravel routes and content contracts unchanged. Replace the public visual layer with one reference-specific stylesheet and a structurally rebuilt homepage hero; shared public header/footer inherit the same system. New generated assets supply the perspective and overlap that CSS cannot create.

**Tech Stack:** Laravel Blade, Vite CSS, PHPUnit feature tests, built-in image generation, headless Chromium visual checks.

---

### Task 1: Lock the two invariants and new hero contract

**Files:**
- Modify: `tests/Feature/PublicHomePageTest.php`

- [ ] Add a test that requests `/`, asserts `dir="rtl"`, asserts `hero-reference`, `hero-device-angle`, and `hero-report-float`, and rejects `/[٠١٢٣٤٥٦٧٨٩]/u` in visible homepage HTML.
- [ ] Run `php artisan test tests/Feature/PublicHomePageTest.php` and confirm the new test fails before implementation.

### Task 2: Create the reference-specific image set

**Files:**
- Create: `public/assets/design/hero-device-angle.png`
- Create: `public/assets/design/hero-report-float.png`
- Create: `public/assets/design/hero-owner-wrist.png`

- [ ] Generate each asset separately with the supplied screenshot as composition reference and the current generated device/report files as identity references.
- [ ] Inspect perspective, subject separation, forbidden text, and background uniformity.
- [ ] Copy final files into `public/assets/design/` without deleting the existing assets.

### Task 3: Rebuild the homepage hero and normalize its digits

**Files:**
- Modify: `resources/views/home.blade.php`

- [ ] Replace the current `hero-v2` object cluster with `hero-reference`, preserving routes, labels, structured content, and the `layout-hero` contract.
- [ ] Place copy at physical right, diagonal art at physical left, and secondary evidence below the divider.
- [ ] Replace hard-coded Arabic-Indic homepage digits with `0-9`; retain `Num` for dynamic values.
- [ ] Run the focused feature test and confirm it passes.

### Task 4: Replace the public visual layer

**Files:**
- Create: `resources/css/reference-ui.css`
- Modify: `resources/css/app.css`
- Modify: `resources/views/partials/site-header.blade.php`
- Modify: `resources/views/partials/site-footer.blade.php`

- [ ] Import `reference-ui.css` last and scope it to `body[data-layout='marketing']` with sufficient specificity to beat older imported layers.
- [ ] Implement the white canvas, heavy display typography, diagonal overlap, localized lime/yellow/pink field, thin rules, compact section rhythm, and cardless rows from the reference.
- [ ] Simplify the public header to the reference hierarchy while keeping all destinations in desktop or mobile navigation.
- [ ] Restyle the footer as a white ruled information band.

### Task 5: Verify build, behavior, and visual fidelity

**Files:**
- Test: `tests/Feature/PublicHomePageTest.php`
- Test: `tests/Feature/AdaptiveInterfaceLayoutTest.php`

- [ ] Run `npm run build` and expect exit code 0.
- [ ] Run `php artisan view:clear`.
- [ ] Run the two focused PHPUnit files and expect zero failures.
- [ ] Render the homepage at desktop and mobile widths, verify no horizontal overflow, and compare the diagonal axis, text/art placement, fade origin, and number glyphs against the supplied reference.
