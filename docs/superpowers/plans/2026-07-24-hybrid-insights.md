# Hybrid In-Wizard Insights Implementation Plan

**Goal:** Give web and Flutter users useful live guidance while they fill a tool, combining immediate deterministic checks with one optional compact AI interpretation after a completed step.

**Architecture:** A single `HybridInsightService` merges saved and unsaved answers, calculates deterministic completeness/readiness/signals locally, and optionally requests a cached economy-tier structured insight. Web and API controllers expose the same payload. The AI branch is explicitly preliminary and can fail without affecting saving or navigation.

## Task 1: Deterministic insight contract

- Add a failing feature test for completeness, agency readiness, a contradiction, and a budget/CAC calculation.
- Implement `HybridInsightService::preview`.
- Add the insight payload to `RunPresenter::wizard`.

## Task 2: Compact AI layer

- Add a failing test with a mocked provider response.
- Define a strict four-field JSON schema.
- Run AI only when `include_ai` is requested, cache by normalized answer hash, record usage as `micro-insight`, and catch all provider failures.

## Task 3: Shared endpoints

- Add authenticated web and API POST endpoints for run insights.
- Enforce run ownership and throttle requests.
- Add feature tests for both endpoint shapes and failure fallback.

## Task 4: Web side panel

- Add a maximum-three-card sticky insight panel beside the wizard.
- Debounce form changes and request deterministic previews.
- Request the compact AI insight only after a saved step is shown.
- Keep form submission independent of insight errors.

## Task 5: Flutter bottom card

- Add insight models and repository method.
- Debounce draft changes for deterministic previews.
- Show a collapsible bottom card and request compact AI after saving a step.
- Keep navigation independent of insight errors.

## Task 6: Verification

- Run targeted PHP and Flutter tests.
- Run Pint, Flutter analyze, full Laravel tests, full Flutter tests, Vite build, and Flutter debug APK build.
