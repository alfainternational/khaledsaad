# Billing, Plan, and Consultation Completion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver one complete web, API, and Flutter release with visible smart diagnosis, correct multi-format forms, assignable plans, and selectable production payment gateways.

**Architecture:** Laravel remains the source of truth. Subscription mutations go through one locked `SubscriptionManager`, gateway adapters implement one expanded payment contract, and Blade plus Flutter consume the same presenter/API shapes. Existing records are migrated in place; no seed or migration rewrites live subscriptions.

**Tech Stack:** Laravel 12, MySQL/SQLite tests, Blade/Vite, Flutter/Dart, Laravel HTTP client, encrypted Eloquent casts.

---

## File structure

- `database/migrations/2026_07_26_160000_complete_billing_and_payment_lifecycle.php`: additive subscription, gateway, payment, refund, and audit fields/tables.
- `app/Models/Subscription.php`, `app/Models/PaymentGateway.php`, `app/Models/Payment.php`, `app/Models/PaymentRefund.php`, and `app/Models/BillingAudit.php`: lifecycle and audit records.
- `app/Services/Billing/SubscriptionManager.php` and `app/Services/Billing/SubscriptionAssignmentService.php`: customer and admin plan transitions.
- `app/Contracts/Payments/PaymentProvider.php`: checkout, verify, health-check, webhook, and refund contract.
- `app/Services/Payments/MoyasarProvider.php`, `app/Services/Payments/TapProvider.php`, `app/Services/Payments/PayPalProvider.php`, `app/Services/Payments/ManualProvider.php`, `app/Services/Payments/PaymentGatewayManager.php`, and `app/Services/Payments/CheckoutService.php`: provider adapters and fulfillment.
- `app/Http/Controllers/Webhooks/MoyasarWebhookController.php`, `app/Http/Controllers/Webhooks/TapWebhookController.php`, and `app/Http/Controllers/Webhooks/PayPalWebhookController.php`: authenticated notifications.
- `app/Http/Controllers/Admin/AdminUserController.php`, `app/Http/Controllers/Admin/AdminGatewayController.php`, and `app/Http/Controllers/Admin/AdminPaymentController.php`: web administration.
- `app/Http/Controllers/Api/V1/Admin/UserController.php`, `app/Http/Controllers/Api/V1/Admin/BillingCatalogController.php`, and `app/Http/Controllers/Api/V1/Admin/PaymentController.php`: mobile administration.
- `app/Http/Controllers/App/BillingController.php`, `app/Http/Controllers/App/CheckoutController.php`, and `app/Http/Controllers/Api/V1/AccountController.php`: customer billing.
- `app/Services/Consultations/Catalog/ConsultationCatalogValidator.php`, `app/Services/Consultations/Engine/AnswerValidator.php`, `app/Services/Consultations/ConsultationPresenter.php`: expanded answer contract.
- `resources/views/app/consultations/*`, `resources/views/partials/panel-nav.blade.php`, `resources/views/app/dashboard.blade.php`, `resources/views/app/projects/*`, `resources/views/home.blade.php`: visible diagnosis and all answer widgets.
- `mobile/lib/features/{consultations,account,admin,projects}/*`: Flutter parity.
- `tests/Feature/*Billing*`, `tests/Feature/*Payment*`, `tests/Feature/*Consultation*`, and `mobile/test/features/*`: regression evidence.

### Task 1: Add lifecycle schema without changing live assignments

**Files:**
- Create: `database/migrations/2026_07_26_160000_complete_billing_and_payment_lifecycle.php`
- Create: `app/Models/PaymentRefund.php`
- Create: `app/Models/BillingAudit.php`
- Modify: `app/Models/Subscription.php`
- Modify: `app/Models/PaymentGateway.php`
- Modify: `app/Models/Payment.php`
- Test: `tests/Feature/BillingLifecycleMigrationTest.php`

- [ ] Write a failing migration test asserting existing subscription IDs and plan IDs survive while the new fields exist.
- [ ] Run `php artisan test tests/Feature/BillingLifecycleMigrationTest.php` and confirm the missing columns fail.
- [ ] Add subscription period/source fields; gateway default/health fields; payment gateway ID, idempotency, lifecycle and refund totals; `payment_refunds` and `billing_audits` tables with indexes and foreign keys.
- [ ] Add casts, relationships, status constants, and safe labels to the models.
- [ ] Re-run the migration test and confirm it passes.

The migration must be additive. New subscription fields are nullable or receive values derived from the existing row without changing `plan_id`. Existing payment `provider` remains readable while `payment_gateway_id` is backfilled by provider when a matching row exists.

### Task 2: Make plan transitions idempotent and auditable

**Files:**
- Modify: `app/Services/Billing/SubscriptionManager.php`
- Create: `app/Services/Billing/SubscriptionAssignmentService.php`
- Modify: `app/Services/Billing/CreditManager.php`
- Test: `tests/Feature/SubscriptionAssignmentTest.php`
- Test: `tests/Feature/CreditLifecycleTest.php`

- [ ] Write failing tests for first free activation, repeated same-plan selection, paid upgrade, scheduled downgrade, expiry, explicit credit policies, single-user assignment, bulk preview, bulk execution, and audit rows.
- [ ] Run the focused tests and verify failures are caused by the missing transition API.
- [ ] Implement `transition(Workspace $workspace, Plan $plan, TransitionOptions $options)` under a transaction with workspace/subscription locks.
- [ ] Grant credits only when the transition policy explicitly requests `plan_grant`, `add`, or `set`; make the first free setup a distinct idempotency key.
- [ ] Implement preview and execution for selected workspace IDs or filters; store before/after snapshots in `billing_audits`.
- [ ] Re-run focused tests and all credit tests.

### Task 3: Complete individual, private-plan, and bulk administration

**Files:**
- Modify: `routes/web.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Admin/AdminUserController.php`
- Modify: `app/Http/Controllers/Admin/AdminPlanController.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/UserController.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/BillingCatalogController.php`
- Modify: `resources/views/admin/users/index.blade.php`
- Modify: `resources/views/admin/users/form.blade.php`
- Create: `resources/views/admin/users/bulk-plan.blade.php`
- Modify: `resources/views/admin/plans/form.blade.php`
- Test: `tests/Feature/AdminSubscriptionAssignmentTest.php`
- Test: `tests/Feature/AdminApiParityTest.php`

- [ ] Write failing web/API authorization, preview, confirmation, private-plan, individual, bulk, schedule, and partial-failure tests.
- [ ] Add routes for assignment preview/execute and subscription actions, all behind `auth`, `admin`, CSRF/Sanctum, and explicit confirmation.
- [ ] Add plan/status/period columns to user lists and all workspaces to user detail.
- [ ] Add public/private plan controls and safe archival; forbid deleting used plans.
- [ ] Add bulk filters by current plan, status, signup range, and explicit user IDs; render exact affected count before commit.
- [ ] Re-run web/API admin tests.

### Task 4: Expand the provider contract and support multiple active gateways

**Files:**
- Modify: `app/Contracts/Payments/PaymentProvider.php`
- Create: `app/Contracts/Payments/GatewayHealth.php`
- Create: `app/Contracts/Payments/WebhookResult.php`
- Create: `app/Contracts/Payments/RefundResult.php`
- Modify: `app/Services/Payments/PaymentGatewayManager.php`
- Modify: `app/Services/Payments/PayPalProvider.php`
- Modify: `app/Services/Payments/ManualProvider.php`
- Create: `app/Services/Payments/MoyasarProvider.php`
- Create: `app/Services/Payments/TapProvider.php`
- Test: `tests/Feature/PaymentProviderContractTest.php`

- [ ] Write contract tests for catalogue credentials, health check, checkout, verify, webhook, refund, currency conversion, and redacted error output.
- [ ] Verify tests fail because Moyasar/Tap and the expanded methods do not exist.
- [ ] Register `moyasar`, `tap`, `paypal`, and `manual`; return all configured active gateways ordered by default then sort order.
- [ ] Implement Moyasar hosted/invoice payment with server-side fetch verification, smallest currency units, shared-secret webhook validation, and refund API.
- [ ] Implement Tap charge redirect with server-side charge retrieval, amount/currency/reference verification, webhook hash verification, and refund API.
- [ ] Preserve PayPal Orders v2 capture/signature flow and add health/refund implementations.
- [ ] Keep manual payments pending until admin approval and implement manual refund recording without an external call.
- [ ] Run provider contract and existing PayPal/manual tests.

### Task 5: Build one-step gateway administration and financial controls

**Files:**
- Modify: `app/Http/Controllers/Admin/AdminGatewayController.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/BillingCatalogController.php`
- Modify: `resources/views/admin/gateways/index.blade.php`
- Modify: `resources/views/admin/gateways/form.blade.php`
- Modify: `app/Http/Controllers/Admin/AdminPaymentController.php`
- Modify: `app/Http/Controllers/Api/V1/Admin/PaymentController.php`
- Modify: `resources/views/admin/payments/index.blade.php`
- Test: `tests/Feature/AdminGatewayManagementTest.php`
- Test: `tests/Feature/PaymentRefundTest.php`

- [ ] Write failing tests proving credentials can be supplied on create, multiple gateways stay active, only one is default, live activation requires a successful health check, secrets never return, used gateways cannot be deleted, and refunds are confirmed/idempotent.
- [ ] Update create/update validation from the provider catalogue and merge only non-empty secret values.
- [ ] Add test-connection and set-default actions; remove the old behavior that deactivates every other gateway.
- [ ] Add payment filters, detail, CSV export, approve/reject, full/partial refund, and refund audit rows.
- [ ] Render callback/webhook URLs and last health result without exposing credentials.
- [ ] Run the focused admin and refund tests.

### Task 6: Let customers choose a gateway and see payment lifecycle

**Files:**
- Modify: `app/Services/Payments/CheckoutService.php`
- Modify: `app/Http/Controllers/App/CheckoutController.php`
- Modify: `app/Http/Controllers/App/BillingController.php`
- Modify: `app/Http/Controllers/Api/V1/AccountController.php`
- Modify: `resources/views/app/billing/index.blade.php`
- Create: `resources/views/app/billing/payment.blade.php`
- Modify: `tests/Feature/CheckoutTest.php`
- Modify: `tests/Feature/CustomerApiParityTest.php`

- [ ] Write failing tests for required gateway selection, invalid/inactive gateway rejection, default selection, callback ownership, webhook completion, payment history/detail, receipt, cancellation, renewal, and no double fulfillment.
- [ ] Pass a `PaymentGateway` into checkout creation and persist its ID; verification always uses the stored gateway even if defaults later change.
- [ ] Return active gateways, current period, scheduled change, and recent payments from web/API billing presenters.
- [ ] Add gateway cards, explicit price/action labels, payment state pages, printable receipts, and manual-reference/evidence input.
- [ ] Disable only paid actions when no electronic/manual gateway is available; free transitions remain functional.
- [ ] Re-run checkout and customer API tests.

### Task 7: Add authenticated webhooks and reconciliation

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Webhooks/PayPalWebhookController.php`
- Create: `app/Http/Controllers/Webhooks/MoyasarWebhookController.php`
- Create: `app/Http/Controllers/Webhooks/TapWebhookController.php`
- Create: `app/Console/Commands/ReconcilePendingPayments.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/PaymentWebhookSecurityTest.php`
- Test: `tests/Feature/PaymentReconciliationTest.php`

- [ ] Write failing tests for signed success, bad signature/token, replay, wrong amount/currency/reference, delayed completion, and pending reconciliation.
- [ ] Add throttled CSRF-exempt provider routes that delegate all trust decisions to the stored gateway adapter.
- [ ] Store webhook IDs in an idempotency ledger before fulfillment and ignore accepted replays.
- [ ] Add a reconciliation command that rechecks stale pending electronic payments without changing manual ones.
- [ ] Schedule reconciliation at a bounded interval with overlap protection.
- [ ] Run webhook and reconciliation tests.

### Task 8: Expand the consultation answer contract and audit every form

**Files:**
- Modify: `app/Services/Consultations/Catalog/ConsultationCatalogValidator.php`
- Modify: `app/Services/Consultations/Catalog/ConsultationCatalogBuilder.php`
- Modify: `app/Services/Consultations/Engine/AnswerValidator.php`
- Modify: `app/Services/Consultations/ConsultationPresenter.php`
- Create: `app/Support/Forms/AnswerType.php`
- Create: `app/Console/Commands/AuditFormAnswerTypes.php`
- Modify: `app/Console/Commands/AuditProductQuality.php`
- Modify: `database/data/tools/agency-brief.php`
- Modify: `database/data/tools/audience-map.php`
- Modify: `database/data/tools/brand-clarity.php`
- Modify: `database/data/tools/campaign-planner.php`
- Modify: `database/data/tools/channel-fit.php`
- Modify: `database/data/tools/competitor-lens.php`
- Modify: `database/data/tools/content-engine.php`
- Modify: `database/data/tools/funnel-audit.php`
- Modify: `database/data/tools/marketing-score.php`
- Modify: `database/data/tools/offer-builder.php`
- Modify: `database/data/tools/seo-compass.php`
- Modify: `tests/Feature/ConsultationCatalogTest.php`
- Create: `tests/Feature/ConsultationAnswerTypesTest.php`
- Create: `tests/Feature/FormAnswerTypeAuditTest.php`

- [ ] Write failing tests for single, multiple, ranking, numeric range/unit, scale, short/long text, URL, email, date, boolean/uncertain, repeated group, file consent, inferred confirmation, unknown, skip, and `other_text`.
- [ ] Add one enum-like registry defining JSON shapes and Laravel validation for every type.
- [ ] Store validation metadata including min/max selections, units, ordering, repeated-item schema, and a required `single_choice_reason` for non-boolean single-choice questions.
- [ ] Version changed consultation questions instead of mutating locked versions; preserve legacy answer rendering.
- [ ] Audit all tool fields and convert questions that allow simultaneous truths to `multiselect`, `ranking`, `repeater`, or `range` with explicit report mappings.
- [ ] Add `product:audit --require-formats` evidence and make unsupported/mismatched widgets fail the release audit.
- [ ] Run catalog, validator, migration, and form-audit tests.

### Task 9: Render every answer type in Blade and make diagnosis obvious

**Files:**
- Create: `resources/views/app/consultations/partials/question-field.blade.php`
- Modify: `resources/views/app/consultations/show.blade.php`
- Modify: `resources/views/app/dashboard.blade.php`
- Modify: `resources/views/app/projects/index.blade.php`
- Modify: `resources/views/app/projects/show.blade.php`
- Modify: `resources/views/partials/panel-nav.blade.php`
- Modify: `resources/views/home.blade.php`
- Modify: `app/Http/Controllers/App/DashboardController.php`
- Modify: `app/Support/Presentation/ProjectPresenter.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/ConsultationVisibilityTest.php`
- Test: `tests/Feature/WebConsultationWidgetsTest.php`

- [ ] Write failing tests for dashboard/nav/public/project entry points, no-project redirect, unfinished-session resume, visible state labels, and HTML controls for every answer type.
- [ ] Extract one reusable Blade partial used for new answers and revisions so stored multi-values, ordering, ranges, and other text round-trip.
- [ ] Add a primary diagnosis card and navigation link; annotate each project with its latest consultation state and next action.
- [ ] Route users without projects through project creation with a signed return target, then start consultation after creation.
- [ ] Add accessible touch controls, instructions for multi-selection, per-field errors, and responsive ranking/repeater UI.
- [ ] Run visibility, widget, public-home, and web-journey tests; build Vite.

### Task 10: Complete Flutter customer and admin parity

**Files:**
- Modify: `mobile/lib/features/consultations/models.dart`
- Modify: `mobile/lib/features/consultations/consultation_screen.dart`
- Create: `mobile/lib/features/consultations/question_field.dart`
- Modify: `mobile/lib/features/projects/dashboard_screen.dart`
- Modify: `mobile/lib/features/projects/project_screen.dart`
- Modify: `mobile/lib/features/account/models.dart`
- Modify: `mobile/lib/features/account/billing_screen.dart`
- Modify: `mobile/lib/features/admin/admin_hub_screen.dart`
- Modify: `mobile/lib/core/api/platform_repository.dart`
- Test: `mobile/test/features/consultation_journey_test.dart`
- Create: `mobile/test/features/consultation_answer_types_test.dart`
- Create: `mobile/test/features/billing_gateway_selection_test.dart`
- Create: `mobile/test/features/admin_subscription_assignment_test.dart`

- [ ] Write failing widget/model tests for every answer shape, visible diagnosis entry/state, gateway selection, payment history, individual/bulk assignment preview and confirmation, gateway health/default/toggle, and refund confirmation.
- [ ] Add typed models that retain JSON shape and validation metadata without flattening arrays/ranges.
- [ ] Build reusable widgets for checkbox groups, ranking, ranges, scales, repeaters, other text, and inferred confirmation; reuse them during revision.
- [ ] Add prominent dashboard navigation and resume behavior.
- [ ] Expand billing and admin screens to consume the new APIs while keeping secrets write-only.
- [ ] Run `flutter analyze` and all Flutter tests.

### Task 11: Apply locally, seed only missing catalogue rows, and verify the unified release

**Files:**
- Modify: `database/seeders/PaymentSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `docs/product/parity-matrix.yaml`
- Modify: `docs/product/release-evidence/consultation-release/README.md`
- Modify: `deploy/release.ps1`

- [ ] Add idempotent seed rows for Moyasar and Tap as inactive/unconfigured; never overwrite stored credentials, active flags, plans, subscriptions, or payments.
- [ ] Back up the local MySQL database and record path/hash before migration.
- [ ] Run `php artisan migrate --force` and the catalogue seeders; confirm zero pending migrations and the original user remains on the original plan.
- [ ] Run `php artisan test` and confirm zero failures.
- [ ] Run `vendor/bin/pint --test`, `npm run build`, and `php artisan product:audit --require-verified --require-consultation --require-formats`.
- [ ] Run `flutter analyze`, `flutter test`, `flutter build apk --release`, and `flutter build appbundle --release`.
- [ ] Verify APK signing, copy the final APK to `public/downloads/khaledsaad-growth.apk`, compute SHA-256 hashes, and confirm the public download test.
- [ ] Smoke test public, customer, admin, checkout-safe, webhook-rejection, consultation, and download routes while the site is out of maintenance mode.
- [ ] Compare every requirement in the design against automated or manual evidence; report any external credential/store limitation explicitly and do not call it complete.
