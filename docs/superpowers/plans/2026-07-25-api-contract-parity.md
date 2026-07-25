# API Contract Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose every visitor, customer, and administrator capability required by the parity matrix through authenticated, versioned, server-authoritative JSON contracts.

**Architecture:** Existing Laravel services and presenters remain the business-logic layer. New API controllers adapt those services into a consistent `{data, meta?, message?}` envelope. Visitor trials use an opaque `X-Guest-Token` whose hash is stored server-side; administrator routes require both Sanctum authentication and the existing `admin` middleware.

**Tech Stack:** Laravel 13, Sanctum 4, PHPUnit 12, SQLite test database.

---

### Task 1: Add public bootstrap and legal-content contracts

**Files:**
- Create: `app/Http/Controllers/Api/V1/PublicContentController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/PublicApiParityTest.php`

- [ ] Write a failing test requiring `api.v1.public.bootstrap`, `api.v1.public.legal`, public brand data, tool cards, entry tool, contacts, and neutral legal copy.
- [ ] Run the test and confirm route-not-found failures.
- [ ] Implement `bootstrap()` from `config('brand')`, `ToolShowcase::cards()`, `stats()`, and `entryTool()`.
- [ ] Implement `legal(string $page)` with an explicit `privacy|terms` allowlist and the same source content used by `LegalController`.
- [ ] Run the focused test and current `PublicHomePageTest`.
- [ ] Update public-content evidence in `docs/product/parity-matrix.yaml`.
- [ ] Commit.

### Task 2: Add mobile-safe password reset contracts

**Files:**
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/PublicApiParityTest.php`

- [ ] Write failing tests proving reset-link requests return the same response for known and unknown emails, and valid reset tokens change the password.
- [ ] Add throttled `auth/forgot-password` and `auth/reset-password` routes.
- [ ] Use Laravel's password broker and `PasswordReset` event without revealing account existence.
- [ ] Return 422 for an invalid or expired token using the neutral error envelope.
- [ ] Include `is_admin` in the server-authoritative user payload.
- [ ] Run auth API and web password-reset regressions.
- [ ] Commit.

### Task 3: Add token-based guest trial API

**Files:**
- Modify: `app/Services/Guests/GuestSessionManager.php`
- Create: `app/Http/Controllers/Api/V1/GuestRunController.php`
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/PublicApiParityTest.php`

- [ ] Write failing tests for starting a runnable tool, receiving a plaintext token once, loading a run with `X-Guest-Token`, saving each step, viewing preflight/result, and rejecting another token with 404.
- [ ] Add `startForApi()`, `currentForApi()`, and `claimForApi()` while storing only the SHA-256 token hash.
- [ ] Return the token only when the API session is created; reuse a valid supplied token without rotating it.
- [ ] Adapt `ToolRunService` and `RunPresenter` into JSON actions without duplicating scoring or conditional-field logic.
- [ ] Accept the guest token during registration and atomically transfer the existing workspace and runs.
- [ ] Run guest web and API journeys together.
- [ ] Update guest-trial evidence in the parity matrix and commit.

### Task 4: Add shared-report JSON contracts

**Files:**
- Create: `app/Http/Controllers/Api/V1/SharedAgencyReportController.php`
- Modify: `routes/api.php`
- Modify: `tests/Feature/PublicApiParityTest.php`

- [ ] Write failing tests for valid, expired, revoked, and unknown tokens.
- [ ] Reuse `AgencyReportSharing::resolve()`, `record()`, and `dataFile()`.
- [ ] Remove `owner_guide` before responding and never reveal whether an invalid token once existed.
- [ ] Add a PDF response using the existing generator.
- [ ] Run sharing tests and update matrix evidence.
- [ ] Commit.

### Task 5: Prove all existing customer contracts

**Files:**
- Create: `tests/Feature/CustomerApiParityTest.php`
- Modify: customer API controllers only when a failing contract exposes a real gap.
- Modify: `docs/product/parity-matrix.yaml`

- [ ] Generate assertions for every customer API route named in the parity matrix.
- [ ] Test owner success, stranger 404, feature denial, validation 422, empty data, and success data for projects, tools, runs, files, competitors, reports, tasks, KPI, Pulse, GEO, audience, agency reports, billing, checkout, and notifications.
- [ ] Add missing notification `read-all`, checkout cancellation status, report data-file, and GEO `llms.txt` equivalents if the generated coverage test proves them absent.
- [ ] Run all existing API, growth, billing, and report tests.
- [ ] Mark API evidence verified only where the route and behavioral test both pass.
- [ ] Commit.

### Task 6: Add administrator read contracts

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/DashboardController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/UsageController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/ToolController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/BillingCatalogController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/UserController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/PaymentController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/ManualReportController.php`
- Create: `app/Http/Controllers/Api/V1/Admin/SettingController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/AdminApiParityTest.php`

- [ ] Write failing tests proving guests receive 401, customers receive 403, and administrators receive all list/detail payloads.
- [ ] Extract reusable payload methods from existing web admin controllers where necessary, preserving their views.
- [ ] Add paginated/searchable JSON read routes for every admin resource.
- [ ] Mask setting secrets and gateway credentials.
- [ ] Run web admin regressions and API read tests.
- [ ] Commit.

### Task 7: Add administrator mutation contracts

**Files:**
- Modify: API admin controllers from Task 6.
- Extract focused services from web controllers when both surfaces mutate the same resource.
- Modify: `tests/Feature/AdminApiParityTest.php`
- Modify: `docs/product/parity-matrix.yaml`

- [ ] Write failing tests for validation, confirmation fields, self-demotion prevention, locked prompt versions, duplicate financial actions, and non-admin denial.
- [ ] Implement tools/status/prompts, features, plans, packs, gateways, users/credits/roles, payments, manual reports, and settings mutations through shared services.
- [ ] Require `confirmation=true` for destructive, role, gateway, and financial mutations.
- [ ] Preserve idempotency for payment approval/rejection and manual-report completion.
- [ ] Run all admin web/API tests and mark proven API records in the matrix.
- [ ] Commit.

### Task 8: Add generated route-coverage enforcement

**Files:**
- Create: `tests/Feature/ApiRouteCoverageTest.php`
- Modify: `app/Support/ProductQuality/ParityMatrix.php`
- Modify: `app/Console/Commands/AuditProductQuality.php`

- [ ] Write a failing test that resolves every non-null API route in the matrix through Laravel's named route collection.
- [ ] Add matrix helpers for records by role/surface.
- [ ] Make `product:audit` report API route coverage separately.
- [ ] Run all API tests, `product:audit`, and Pint.
- [ ] Commit.

## Verification

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\PublicApiParityTest.php tests\Feature\CustomerApiParityTest.php tests\Feature\AdminApiParityTest.php tests\Feature\ApiRouteCoverageTest.php
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ApiParityTest.php tests\Feature\MobileParityGapsTest.php tests\Feature\AdminCrudTest.php tests\Feature\AdminPanelTest.php
php artisan product:audit
vendor\bin\pint --test app\Http\Controllers\Api app\Services\Guests app\Support\ProductQuality tests\Feature\PublicApiParityTest.php tests\Feature\CustomerApiParityTest.php tests\Feature\AdminApiParityTest.php tests\Feature\ApiRouteCoverageTest.php
```

Expected: all commands exit 0; no administrator route is reachable by a customer; no visitor token is stored in plaintext; every non-null matrix API route resolves.
