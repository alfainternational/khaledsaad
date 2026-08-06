# Unified Interface System Design

**Date:** 2026-08-06  
**Status:** Approved direction; written specification awaiting final review  
**Visual authority:** the supplied OLEX reference image  
**Product authority:** `CLAUDE.md`, especially sections 4 and 13  
**Design-token authority:** `STYLESEED.md`

## 1. Objective

Apply one coherent visual system to every human-facing Laravel and Flutter surface without changing product meaning, workflows, permissions, data, or verified copy.

The system is one identity with several density modes:

1. Editorial public marketing.
2. Focused authentication.
3. Operational customer workspace.
4. Dense administration.
5. Documentary reports and exports.
6. Native Flutter equivalents.

This is not a color pass. Every screen must receive an intentional information hierarchy, layout family, empty/error/loading states, action hierarchy, and responsive treatment.

## 2. Confirmed Scope

The coverage boundary is every reachable, human-facing surface in the repository:

- Public site: home, services, methodology, principles, pricing, profile, knowledge/content, sectors, tools, diagnostic trial, legal pages, shared reports, Android download, and public error states.
- Authentication: login, registration, forgotten password, reset password, and verification-related states.
- Customer workspace: dashboard, projects, tools, runs, consultations, reports, readiness, presence, audience, message studio, prospects, pulse, portfolio, agency reports, tasks, billing, notifications, security, search, and learning.
- Administration: dashboard, operations, consultations, content, curriculum, media, subscribers, features, gateways, packs, payments, plans, settings, tools, usage, users, and manual/reference screens.
- Reports: browser reports, shared reports, agency reports, owner reports, print views, and PDFs.
- Flutter: all public, authentication, project, diagnostic, report, growth, portfolio, agency, account, readiness, administrative, and update screens.
- Cross-cutting states: empty, loading, failure, success, disabled, permission-denied, offline/mobile-error, and confirmation states.

Third-party source files are not edited directly. Any third-party output visible to a person must be visually integrated through an application-owned wrapper or token layer.

Deployment to production is outside this design cycle. Local implementation, verification, and Android APK/AAB builds are included.

## 3. Non-Negotiable Rules

### 3.1 Direction and language

- Root direction is RTL on web and Flutter.
- Arabic is the default UI language except product/platform names.
- Displayed digits are Latin `0-9`, never Arabic-Indic digits.
- Directional icons reverse in RTL; pictorial icons do not.
- Existing verified copy does not change as part of visual implementation.
- Line breaks use responsive wrapping, not `<br>` inserted into verified phrases.

### 3.2 Typography

- `IBM Plex Sans Arabic` is the interface family on Laravel and Flutter.
- The approved logo artwork keeps its own lettering.
- Hacen Tunisia is removed from Flutter interface text.
- Four real weights are used: 400, 500, 600, and 700.
- No text is rendered below 13px equivalent.
- Headings use balanced wrapping and the full available text column.
- Numeric columns use tabular lining figures.

### 3.3 Evidence and product semantics

- `measured`, `derived`, and `inferred` retain their official meanings and colors.
- Every changing score shows basis, coverage, and last update when those values exist.
- `inferred` output is visibly labelled `فرضية`.
- Missing evidence remains visible; the interface never fills gaps with invented certainty.
- Planning remains subordinate to diagnosis and never becomes the principal value proposition.

### 3.4 Imagery and icons

- No raster image may appear in two positions anywhere in the public experience.
- Each editorial public section or page that uses artwork receives a unique content-derived image.
- A machine-readable artwork manifest maps each asset to one canonical route and placement.
- Images express the section's subject; they are not generic decoration.
- Public artworks use the established white/pale-metal product-render language with black, lime, and restrained orange accents.
- Workspace and administration use semantic SVG icons, data visuals, and interface diagrams rather than decorative raster art.
- Empty/onboarding illustrations are allowed only when unique to that exact state.
- All non-informational art has empty alt text and is excluded from accessibility semantics.
- Informational charts and report visuals receive meaningful Arabic labels.

## 4. Visual Architecture

### 4.1 Shared foundation

All families share:

- Semantic tokens from `STYLESEED.md` for color, type, spacing, radius, shadow, state, and evidence level.
- A 4px spacing grid.
- Thin rules as the default separator.
- One accent color for interactive emphasis.
- 44x44px minimum interactive targets.
- One primary action per screen region.
- One consistent focus ring and motion budget.
- Light and dark modes with equivalent contrast and hierarchy.

The visual reference contributes composition, asymmetry, typography scale, ruled sections, strong object placement, and restrained color fields. It does not contribute unrelated product objects, English layout direction, or campaign text.

### 4.2 Public editorial family

Public pages use physical-right copy and physical-left visual composition on wide screens. Text remains RTL. Content becomes a sequence of editorial sections rather than a grid of equally weighted cards.

Each page contains, as applicable:

- Compact shared header.
- Page-specific editorial hero.
- Full-width title region using the complete copy column.
- Unique artwork mapped to the page or section.
- Ruled rows with semantic SVG icons and secondary numeric indexing.
- Clear proof/basis blocks.
- One concluding call to action.
- Compact shared footer.

On narrow screens, copy precedes artwork, action hierarchy remains visible, and images use `object-fit: contain` without horizontal overflow.

### 4.3 Authentication family

Authentication screens are focused and low-density:

- Brand and context at the top.
- One page title and short lead.
- One form with clear field grouping.
- Primary submission action followed by the single relevant alternative.
- Inline validation that does not shift controls unpredictably.
- No marketing navigation or decorative public artwork.

### 4.4 Customer workspace family

The workspace is an operational instrument, not a marketing page:

- Persistent RTL navigation shell.
- Compact page header with context, last update, and primary action.
- Responsive main/secondary column hierarchy.
- Flat ruled panels instead of isolated floating cards.
- Metric strips show number, basis, coverage, trend, and evidence level.
- Forms are grouped by decision, not database table.
- Wizards keep visible progress, saved state, and recovery action.
- Empty states explain the next useful action.
- Mobile navigation preserves the same information architecture.

### 4.5 Administration family

Administration shares the workspace identity with higher density:

- Denser tables and filters without reducing the 13px text floor.
- Sticky table headers where useful.
- Batch actions appear only after selection.
- Destructive actions are visually separated and require explicit confirmation.
- Status and permission information is text plus icon, never color alone.
- Operational anomalies are surfaced before editable controls.
- Admin-to-workspace switching remains persistent and unmistakable.

### 4.6 Reports and document family

Reports must feel authored and decision-ready:

- Executive summary before detail.
- Score, basis, coverage, and evidence level grouped together.
- Findings ordered by impact and effort.
- Tables repeat headers in print.
- Page breaks avoid orphan titles and split evidence blocks.
- Screen, shared-link, and PDF versions use the same section order and terminology.
- PDF uses embedded IBM Plex Sans Arabic regular and bold fonts.
- Decorative public art is not reused in reports.

### 4.7 Flutter family

Flutter is native and uses the same semantics, not a visual imitation of desktop web:

- A single `ThemeData` source defines IBM Plex typography, colors, radii, spacing, focus, and evidence states.
- Shared widgets cover page headers, metric/basis blocks, evidence badges, ruled sections, empty states, action bars, form groups, and error/loading states.
- Bottom navigation and nested navigation preserve current routes and state.
- Public screens follow the editorial hierarchy within mobile constraints.
- Workspace/admin screens follow operational density and thumb reach.
- Latin digits are enforced at presentation boundaries.
- No WebView replaces native screens.

## 5. Component Boundaries

### 5.1 Laravel components

The implementation should consolidate reusable application-owned components around these responsibilities:

- `page-header`: context, title, description, metadata, primary action.
- `section`: heading, ruled body, optional unique art slot.
- `row`: semantic icon, optional index, title, description, metadata/action.
- `metric`: value, denominator/basis, coverage, evidence, updated time.
- `status`: icon, label, tone, accessible text.
- `panel`: flat grouped content surface.
- `data-table`: filters, responsive overflow, selection, batch actions, empty state.
- `form-section`: decision-oriented field grouping and errors.
- `wizard-progress`: current step, saved state, remaining steps, recovery.
- `empty-state`: specific reason and next action.
- `action-bar`: one primary action and ordered secondary actions.
- `report-section`: screen/print-compatible heading and body.
- `artwork`: manifest-resolved unique public asset.

Components expose semantic slots and variants. Individual pages retain their content and business conditions.

### 5.2 CSS layers

CSS responsibilities remain separated:

1. Tokens and base typography.
2. Shared primitives and accessibility.
3. Public editorial family.
4. Authentication family.
5. Workspace family.
6. Administration density overrides.
7. Report/print family.
8. Responsive and reduced-motion behavior.

Selectors are scoped by layout and audience attributes. Import order is not treated as authority; intentional specificity is used without broad `!important` locks.

### 5.3 Flutter components

Flutter widgets mirror semantic responsibilities, not Blade markup. Web and Flutter may differ structurally while preserving the same hierarchy, terminology, evidence meaning, and interaction priority.

## 6. Data and Interaction Flow

Visual work does not alter controllers, routes, authorization, validation, persistence, or API schemas unless an existing UI cannot express its current state safely.

Every screen follows this sequence:

1. Resolve current user, workspace/project, permission, and route state.
2. Present the page context and primary decision.
3. Present evidence and coverage before recommendations.
4. Present the next allowed action.
5. Preserve existing form submissions and navigation destinations.
6. Render explicit loading, empty, error, and permission states.

No live data is reset, inferred, or silently repaired by the redesign.

## 7. Responsive Behavior

Required verification widths:

- 390px mobile.
- 768px tablet.
- 1280px laptop.
- 1440px and wider desktop.

At every width:

- No horizontal page overflow.
- Tables use contained horizontal scrolling or responsive row presentation.
- Fixed actions never cover content.
- Navigation remains keyboard and touch accessible.
- Public artwork is centered in its allocated space.
- Titles use available width without forced line breaks.

## 8. Accessibility and Quality Gates

Every family must pass:

- RTL root and correct reading order.
- Latin digits only in rendered UI.
- Minimum text floor of 13px equivalent.
- WCAG AA contrast for text and controls.
- 44x44px interaction targets.
- Visible focus state.
- Keyboard access to navigation, dialogs, tables, forms, and accordions.
- Labels for controls and charts.
- Reduced-motion support.
- No console errors or missing assets.
- Light/dark parity where the family supports both.

## 9. Coverage and Regression Strategy

### 9.1 Coverage registry

A committed registry records every human-facing Blade view, route family, Flutter screen, assigned layout family, verification state, and visual-review evidence. Completion is based on this registry, not a self-reported count alone.

### 9.2 Automated Laravel checks

Tests must establish:

- All route families render through an approved layout family.
- All page titles and existing protected copy remain present.
- RTL and Latin digit contracts hold.
- No public design image is referenced more than once.
- Every public art reference exists and is uniquely mapped.
- No forbidden interface font remains.
- No component contains direct visual constants outside approved token definitions.
- Representative authenticated and admin routes render for correctly authorized users.
- Report screen/shared/PDF section order remains aligned.

### 9.3 Automated Flutter checks

Tests must establish:

- IBM Plex typography is the active interface theme.
- Shared evidence, metric, empty, error, and action widgets render correctly.
- RTL and Latin digits hold in representative public, workspace, admin, and report screens.
- Existing navigation destinations and API-backed states remain reachable.
- `flutter analyze` and the full widget/unit test suite pass.

### 9.4 Visual review

Representative routes from every family are inspected at required widths in both supported themes. Long tables, long Arabic titles, validation errors, empty states, and loading states are included. A running page is not accepted as proof without these checks.

## 10. Implementation Decomposition

Because the scope spans independent subsystems, implementation is divided into testable releases:

1. **Coverage and guardrails:** registry, artwork manifest, layout contracts, and failing tests.
2. **Shared foundation:** tokens, typography, icons, primitives, page header, rows, panels, states, and responsive rules.
3. **Public site:** all public pages, content, sectors, tools, trial, shared links, legal, errors, and unique artwork.
4. **Authentication:** all account entry and recovery screens.
5. **Customer workspace:** all 42 workspace views and their states.
6. **Administration:** all 32 administrative views and their states.
7. **Reports:** browser, shared, agency, owner, print, and PDF parity.
8. **Flutter foundation and screens:** theme migration, shared widgets, then all 37 screens.
9. **Cross-surface verification:** full Laravel/Flutter tests, browser checks, responsive checks, APK/AAB build, version/signature/SHA-256 verification.

Each release begins with a failing coverage or behavior test and ends with fresh focused and regression verification. Existing unrelated dirty-worktree changes are preserved and excluded from intentional commits.

## 11. Definition of Complete

The request is complete only when all of the following are true:

- Every human-facing route/view/screen is listed in the coverage registry.
- Every registry entry is assigned to an approved family and marked verified by code/test evidence.
- Every public raster artwork has one unique canonical placement and exists on disk.
- No public artwork is repeated in another position.
- Laravel public, authentication, workspace, admin, report, legal, and error families pass their contract tests.
- All Flutter screens use the unified native theme and shared semantic components.
- RTL, Latin digits, typography, contrast, touch targets, focus, responsive behavior, and evidence semantics pass their gates.
- Browser inspection reports no missing assets, console errors, or horizontal overflow on representative routes.
- Laravel build/tests and Flutter analyze/tests pass.
- New APK and AAB artifacts are built and independently verified by version, signature, and SHA-256.
- No production deployment is claimed without a separate deployment action and live verification.

