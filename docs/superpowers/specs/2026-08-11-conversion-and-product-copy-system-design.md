# Conversion and Product Copy System Design

**Date:** 2026-08-11

**Status:** Proposed for user review

**Scope:** Laravel public and authenticated web, API-presented labels and messages, generated diagnostic results, and matching Flutter surfaces in Arabic, English, and French.

## Goal

Rewrite the platform's human-facing copy so that acquisition surfaces convert honestly, authenticated surfaces are simple and action-oriented, diagnostic tools and results are understandable in every enabled interface language, and the Arabic-only marketing course remains educational rather than being converted into sales content.

The result should help a non-specialist answer three questions without marketing knowledge:

1. What is this page asking me to do?
2. Why does it matter to my project?
3. What will I receive after I do it?

## Product Positioning

The platform serves an Arabic-speaking business owner who needs to understand the actual state of the project's marketing before spending more money or handing work to an employee, freelancer, or agency. It does not promise sales, rankings, or campaign performance. It promises a clearer diagnosis, named gaps, evidence and assumptions that are kept separate, ordered next steps, and a handoff that another person can execute.

The learning path remains inside the same platform but has a different job: teach marketing through clear Arabic lessons and practical applications. It must not be written as an acquisition funnel once the learner enters the course.

## Non-goals

- Do not rewrite educational lessons as sales content.
- Do not translate marketing-course lesson bodies, exercise teaching content, or educational feedback into English or French.
- Do not add fake urgency, fabricated scarcity, guaranteed outcomes, unsupported superlatives, or pressure tactics.
- Do not turn reports, security, billing, validation, errors, or administration into promotional surfaces.
- Do not translate stable database keys, metric keys, option values, JSON keys, matching lexicons, scoring rules, or model prompt contracts.
- Do not create a second content catalog, academy, application, workspace, or source of truth.
- Do not retroactively rewrite historical reports as if they had originally been generated in another language.

## Editorial Layers

### 1. Acquisition and conversion

**Surfaces:** public home, sector pages, public tool pages, methodology, services, pricing, sample report, registration entry points, and experience selection.

**Purpose:** move a qualified visitor to one honest next action.

**Pattern:** recognizable problem → useful outcome → proof or basis → risk removal → one primary CTA.

**Voice:** direct, concrete, warm, and confident. Lead with the user's situation, not with the platform or artificial intelligence.

**Examples of acceptable claims:**

- “اعرف أين يتعطّل تسويق مشروعك، وما الذي يستحق أن تبدأ به.”
- “أجب عن أسئلة واضحة، واحصل على فجوات مرتبة وخطوات يمكنك تنفيذها أو تسليمها لمن ينفذ.”
- “ابدأ من دون حساب، ثم احفظ نتيجتك إذا وجدتها مفيدة.”

**Claims to reject:**

- “ضاعف مبيعاتك.”
- “تصدّر جوجل.”
- “احصل على عملاء مضمونين.”
- “أفضل منصة تسويق.”

Two entry intents remain visible where relevant:

- **شخّص مشروعي** / Diagnose my project / Diagnostiquer mon projet.
- **ابدأ تعلم التسويق** / Start learning marketing / Commencer à apprendre le marketing.

The business CTA is primary on diagnosis-oriented pages. The learning CTA is secondary and leads to the Arabic course with a language notice when the interface is not Arabic.

### 2. Intent, authentication, and onboarding

**Surfaces:** experience choice, registration, login, password recovery, first project, activation, and cross-experience switching.

**Purpose:** remove uncertainty about what happens after the user continues.

Every screen states:

- what is needed now;
- why it is needed;
- what comes next;
- whether progress is saved;
- whether the choice can be changed later.

Avoid account jargon such as roles, entitlements, capabilities, or workspace ownership. Use “ما الذي تريد أن تفعله الآن؟” and explain the two journeys in outcome language.

### 3. Business workspace

**Surfaces:** dashboard, projects, consultations, diagnostic catalog and wizards, tasks, reports, KPIs, weekly pulse, advanced project tools, notifications, billing, and account settings.

**Purpose:** help the owner make and execute one decision at a time.

Copy rules:

- One idea per sentence and one required action per screen region.
- Start headings with the decision or result, not the internal feature name.
- Explain marketing terminology in the same sentence in which it appears.
- Replace vague counts such as “20 نتيجة جاهزة” with the actual object: reports, recommendations, tasks, or completed applications.
- Replace raw statuses such as `active`, `launch`, `queued`, or `partial` with translated, user-facing labels.
- Every diagnostic entry tells the user what it asks for, approximate time, output, credit cost where applicable, and whether prior answers are reused.
- Every long-running state tells the user what is happening, what can be done meanwhile, when delay becomes abnormal, and how to recover.
- Empty and error states contain the smallest safe next action, not blame or generic failure language.
- Advanced tools do not imply that AI-readiness, GEO, or synthetic audiences are prerequisites for a basic marketing diagnosis.

### 4. Diagnostic questions and assistance

**Surfaces:** tool titles, descriptions, pain, promise, audience, field labels, help, why, examples, step titles, option labels, review screens, and contextual assistance.

**Purpose:** make a non-marketer able to answer accurately without guessing what the platform wants.

Question pattern:

1. Ask one thing only.
2. Use the words the owner would use, not academic marketing vocabulary.
3. Add a concrete example when the answer format could be misunderstood.
4. Explain why the answer changes the diagnosis.
5. Allow “لا أعرف” when uncertainty is meaningful and preserve it as missing evidence rather than a zero score.

Assistance must help the user understand the field. It must not silently write or save an answer as if it were the user's fact.

### 5. Reports and generated results

**Surfaces:** report summary, score basis, findings, assumptions, recommendations, tasks, shares, PDFs, notifications, and Flutter report views.

**Purpose:** make the result credible, readable, and executable.

Report order:

1. Current situation in plain language.
2. The three highest priorities.
3. One action for this week.
4. Information that still needs confirmation.
5. Evidence and score basis.
6. Detailed findings and recommendations.

Keep `measured`, `derived`, and `inferred` distinct. Explain them in the selected language and show missing coverage rather than inventing certainty. Generated outputs follow `ToolRun.locale` and `Report.locale`; the interface locale does not rewrite a historical result.

English and French generated reports use the locale's generation tone from `config/locales.php`. Brand names, platform names, metric keys, and JSON keys remain stable.

### 6. Learning experience

**Surfaces:** course index chrome, lesson shell, progress, application shell, navigation, and language notices.

**Purpose:** teach, guide practice, and give understandable feedback in Arabic.

The course body, lesson titles, teaching explanations, educational application questions, examples, rubrics, and educational feedback remain Arabic-only. They are reviewed for simplicity, natural flow, and instructional clarity, never for conversion pressure.

When the account interface is English or French:

- the surrounding account/navigation chrome may use the selected interface language;
- the course entry displays a clear notice that the learning content is currently available in Arabic;
- the lesson and its applications open as Arabic content with `lang="ar"` and `dir="rtl"` on the content region;
- do not present automated English/French lesson translations;
- do not mix an untranslated Arabic lesson into a foreign interface without the language notice.

### 7. Trust and operational copy

**Surfaces:** privacy, terms, security, payment, credit ledger, validation, errors, destructive confirmations, administration, and system health.

**Purpose:** accuracy, safety, and recovery.

Use neutral, factual language. State what happened, what changed, whether money or data moved, and the next safe action. Billing should translate technical reservations and settlements into understandable activity, while retaining auditable details in a secondary view. Administration keeps domain terminology when the operator needs it, with short explanations instead of promotional rewriting.

## Language System

### Arabic source

Arabic is the canonical source for interface copy and diagnostic content. Use a professional white Arabic understandable across the Gulf and wider Arabic-speaking market, with a light Gulf familiarity but no heavy local slang. Address the user as “أنت”. Prefer short sentences, active verbs, and concrete nouns.

Do not use inflated formal phrases, internal AI vocabulary, or literal English marketing jargon when a familiar Arabic phrase exists.

### English adaptation

Use plain B2B English addressed directly as “you”. Prefer sentence case, short verbs, and outcome language. Do not translate Arabic idioms literally. Explain every marketing term when first used. Avoid SaaS hype such as unlock, supercharge, revolutionize, or game-changing.

### French adaptation

Use professional, direct French with consistent `vous`. Preserve correct French punctuation and spacing. Avoid literal English constructions and inflated consultant language. Keep CTA labels short enough for mobile while retaining the actual action.

### Translation quality

- Arabic is reviewed first; English and French are adaptations of the approved meaning.
- A translation is not complete merely because a key exists.
- Preserve placeholders, tags, option values, and stable identifiers.
- Review long English/French text at mobile widths.
- Mark provenance and human review status using the current i18n system.
- `i18n:audit --strict` must reject missing keys, placeholder drift, Arabic residue on English/French interface surfaces, and excessive expansion where it breaks the UI.

## Dynamic Tool Localization Architecture

Static interface copy continues to use the existing Arabic-key JSON catalog and `config/locales.php` registry.

Database-backed diagnostic content needs version-aware translations because the current `tools`, `tool_versions`, and `tool_fields` tables store Arabic human-readable text.

Add focused translation tables rather than copying complete tools or translating runtime keys:

### `tool_translations`

- `tool_id`
- `locale`
- translated `name`, `title`, `description`, `pain`, `promise`, and `audience`
- `source_text_hash`
- `translator`, `reviewed_by`, `reviewed_at`
- unique `(tool_id, locale)`

### `tool_field_translations`

- `tool_field_id`
- `locale`
- translated `label`, `help`, `why`, `example`, and `step_title`
- `option_labels` JSON keyed by the original stable option `value`
- `source_text_hash`
- review metadata
- unique `(tool_field_id, locale)`

### `tool_version_translations`

- `tool_version_id`
- `locale`
- translated human-readable section-plan titles/descriptions keyed by stable section keys
- `source_text_hash`
- review metadata
- unique `(tool_version_id, locale)`

Do not translate:

- `tools.key`;
- field keys, types, validation, `visible_when`, and `profile_key`;
- option values;
- output schema keys;
- scoring-rule keys and weights;
- prompt stage/tier/status;
- prompt contracts themselves.

`ToolPresenter` and API presenters resolve the selected locale through one translation resolver and fall back to Arabic only as a safety mechanism. Production release for an enabled locale must fail coverage checks if a published tool lacks reviewed user-facing translations; normal English/French screens should not silently mix Arabic tool questions.

Admin editing exposes translation status, source changes, and stale translations. Changing canonical Arabic invalidates the matching source hash but does not delete the previous translation or mutate a locked prompt/version.

## Generated Output and Locale Flow

- Capture the user's locale when a tool run starts.
- Carry it through queue payloads, retries, manual review, notifications, reports, shares, and PDFs.
- Use `GenerationLocale` at the structured-generation boundary.
- Do not translate a report after generation as a substitute for generating it in the correct language.
- Historical reports remain in their original `locale` and display an explicit content-language label when the surrounding interface differs.
- Human reviewers see the report's language and must not accidentally regenerate it in the admin session language.

## Flutter Parity

Flutter consumes the same localized API presenter data and stable codes as the web application. Do not duplicate tool copy in Dart.

New or modified Flutter chrome uses the app's centralized ARB/localization layer for Arabic, English, and French. Tool labels, questions, options, statuses, and results come from the API in the requested locale. Educational lesson content remains Arabic with an explicit content-language notice and RTL content region.

## Content Inventory and Rewrite Workflow

Create a machine-readable inventory that maps every human-facing string to:

- surface and route/screen;
- source file/table and stable identifier;
- editorial layer;
- Arabic source status;
- English and French status;
- evidence/claim requirement;
- human reviewer status.

Rewrite in this order:

1. claim and terminology guardrails;
2. public acquisition and registration;
3. business dashboard and primary journeys;
4. diagnostic catalog, questions, and states;
5. reports, tasks, billing, security, and errors;
6. learning chrome plus Arabic instructional review;
7. English adaptation;
8. French adaptation;
9. Flutter parity and responsive review.

Do not perform blind global substitutions. The same Arabic word can require different English or French wording in a CTA, report, validation message, and admin table.

## Verification

### Editorial tests

- Reject prohibited guarantee and hype phrases on acquisition surfaces.
- Require one primary CTA per conversion region.
- Require every diagnostic card to expose time, output, and cost when applicable.
- Require every question to expose help/why/example when its schema marks them available.
- Reject raw internal status codes on human-facing views and API payload labels.
- Confirm reports preserve evidence vocabulary and missing coverage.

### Localization tests

- All enabled interface locales render representative public, auth, business, diagnostic, report, billing, and error routes without source-language residue.
- Published diagnostic tools have complete reviewed translations for enabled non-source locales.
- Option labels translate while values remain identical.
- Queue-generated reports, notifications, shares, and PDFs retain the run locale.
- Course bodies and educational applications remain Arabic and carry an explicit content-language declaration in English/French interfaces.
- Flutter and web receive the same localized tool labels and status semantics.

### Manual review

Review representative journeys in Arabic, English, and French at 390, 768, 1280, and 1440 pixels:

- home → public tool → registration;
- experience choice → first project/first lesson;
- dashboard → diagnostic wizard → result;
- report → task → notification;
- billing and failed-payment recovery;
- course entry from an English/French account.

Check comprehension, claim truthfulness, CTA clarity, keyboard/focus behavior, RTL/LTR, overflow, and whether the user can identify the next action without marketing knowledge.

## Delivery Boundaries

This is one editorial system delivered through coordinated workstreams. Implementation planning may split it into public conversion, authenticated product copy, database-backed tool localization, and generated-result/Flutter parity. Each workstream must remain deployable and testable without introducing a parallel content source.

No production deployment is part of the design approval. Deployment requires a separate verified release step after migrations, translation coverage, focused tests, builds, and live smoke checks pass.
