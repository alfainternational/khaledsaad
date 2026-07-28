# Platform-Wide User Question Rewrite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rewrite every human-facing question in the Laravel web application and Flutter client to the approved conversational Arabic model, preserve answer semantics, and deploy the verified web/backend changes to khaledsaad.net.

**Architecture:** Canonical diagnostic copy remains in `database/data/tools/*.php`, consultation gateway copy remains in `config/consultation.php`, and agency-brief copy remains in `BriefQuestions`. Renderers expose the same question, help/example, answer control, and «لماذا نسأل؟» hierarchy. A new consultation blueprint version imports the revised copy without mutating locked consultation history; option values, keys, types, validation, scoring, and branching remain unchanged.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, PHPUnit, Flutter/Dart, Vite, cPanel SSH deployment.

---

### Task 1: Establish the copy contract and inventory

**Files:**
- Create: `tests/Feature/UserFacingQuestionCopyTest.php`
- Modify: `docs/superpowers/plans/2026-07-28-platform-question-rewrite.md`

- [ ] **Step 1: Write the failing catalog contract**

```php
public function test_every_canonical_question_has_guidance_and_a_reason(): void
{
    foreach (glob(database_path('data/tools/*.php')) as $path) {
        $tool = require $path;
        foreach ($tool['fields'] as $field) {
            $this->assertStringEndsWith('؟', $field['label'], $tool['key'].'.'.$field['key']);
            $this->assertNotEmpty($field['why'] ?? null, $tool['key'].'.'.$field['key']);
        }
    }

    foreach (config('consultation.gateway_questions') as $question) {
        $this->assertStringEndsWith('؟', $question['text'], $question['key']);
        $this->assertNotEmpty($question['help'] ?? null, $question['key']);
        $this->assertNotEmpty($question['why'] ?? null, $question['key']);
    }
}
```

- [ ] **Step 2: Add renderer and vocabulary assertions**

```php
$runField = file_get_contents(resource_path('views/app/runs/partials/field.blade.php'));
$this->assertStringContainsString('<summary>لماذا نسأل؟</summary>', $runField);
$this->assertStringNotContainsString('لماذا نسأل عن هذه؟', $runField);

$visibleSources = implode("\n", array_map(
    fn (string $path) => file_get_contents($path),
    $this->questionFacingSourcePaths(),
));
foreach (['جاوب', 'شغلك', 'عندك', 'أي واحدة من هذه تحصل معك'] as $rejected) {
    $this->assertStringNotContainsString($rejected, $visibleSources);
}
```

- [ ] **Step 3: Run the test and verify RED**

Run: `php artisan test tests/Feature/UserFacingQuestionCopyTest.php`

Expected: FAIL because the twelve gateway questions do not yet contain `help` and `why`, and the run renderer still says «لماذا نسأل عن هذه؟».

- [ ] **Step 4: Record the inventory counts in the test data provider**

The provider must cover the 11 tool-definition files, `config/consultation.php`, `app/Support/Marketing/BriefQuestions.php`, question-facing Blade views under `resources/views`, and question-facing Dart files under `mobile/lib`.

- [ ] **Step 5: Commit the red contract**

Run: `git add tests/Feature/UserFacingQuestionCopyTest.php docs/superpowers/plans/2026-07-28-platform-question-rewrite.md && git commit -m "test: define user question copy contract"`

### Task 2: Rewrite canonical diagnostic, consultation, and agency questions

**Files:**
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
- Modify: `config/consultation.php`
- Modify: `app/Support/Marketing/BriefQuestions.php`

- [ ] **Step 1: Rewrite every canonical label**

Use one clear question ending in `؟`, singular address, and no specialist term without explanation. Keep every `key`, `type`, `required`, `validation`, `visible_when`, and option `value` byte-for-byte unchanged.

- [ ] **Step 2: Rewrite guidance by answer type**

For direct input, provide an example such as `اكتبها بالطريقة التي يمكن أن يقولها العميل.` For single choice, say `اختر الإجابة الأقرب إلى وضع مشروعك الآن.` For multiselect, say `يمكنك اختيار كل ما ينطبق.` Do not add choices to direct-input fields.

- [ ] **Step 3: Rewrite every reason**

Each `why` must explain what the answer reveals and how it changes the diagnosis or recommendation. It must not shame the user, guarantee results, or present an inference as a measured fact.

- [ ] **Step 4: Give all gateway questions complete copy**

```php
[
    'key' => 'START-01',
    'variable' => 'assessment_scope',
    'text' => 'ما الذي تريد أن نراجعه معك في هذا التشخيص؟',
    'help' => 'اختر كل ما تريد تقييمه. يمكنك اختيار المشروع كله أو جزء محدد منه.',
    'why' => 'لأن نطاق التشخيص يحدد الأسئلة التي تحتاجها فعلًا، ويمنع تكرار أسئلة لا تؤثر في قرارك الحالي.',
    'type' => 'multiselect',
    'options' => ['فكرة جديدة', 'مشروع قائم', 'منتج أو خدمة', 'سوق جديد', 'حملة', 'موقع أو تطبيق', 'كل النشاط'],
]
```

- [ ] **Step 5: Run the copy contract**

Run: `php artisan test tests/Feature/UserFacingQuestionCopyTest.php`

Expected: the catalog assertions pass; renderer assertions remain RED until Task 3.

- [ ] **Step 6: Commit canonical copy**

Run: `git add database/data/tools config/consultation.php app/Support/Marketing/BriefQuestions.php && git commit -m "feat: rewrite canonical user questions"`

### Task 3: Align web renderers and static web questions

**Files:**
- Modify: `resources/views/app/runs/partials/field.blade.php`
- Modify: `resources/views/app/consultations/show.blade.php`
- Modify: `resources/views/app/consultations/_answer-field.blade.php`
- Modify: question-facing views under `resources/views/auth`, `resources/views/app/projects`, `resources/views/app/agency-reports`, `resources/views/app/reports`, `resources/views/app/audience`, and `resources/views/admin`
- Modify: `config/brand.php`

- [ ] **Step 1: Present context before the answer control**

Render help and examples after the question title and before the input. Render the reason after the answer control under the exact heading «لماذا نسأل؟».

- [ ] **Step 2: Rewrite every static question and confirmation**

Apply the same voice to public FAQs, account flows, project forms, report requests, audience testing, admin data-entry questions, and destructive confirmations. Preserve form names, routes, validation attributes, and JavaScript hooks.

- [ ] **Step 3: Run focused web journeys**

Run: `php artisan test tests/Feature/UserFacingQuestionCopyTest.php tests/Feature/PublicHomePageTest.php tests/Feature/WebAppJourneyTest.php tests/Feature/ConsultationVisibilityAndAnswerTypesTest.php tests/Feature/AgencyBriefBudgetTest.php`

Expected: PASS with no changed answer semantics.

- [ ] **Step 4: Commit web copy and renderers**

Run: `git add resources/views config/brand.php tests/Feature/UserFacingQuestionCopyTest.php && git commit -m "feat: align web questions with approved model"`

### Task 4: Publish a new consultation copy version safely

**Files:**
- Modify: `config/consultation.php`
- Modify: `app/Services/Consultations/Catalog/ConsultationCatalogBuilder.php`
- Modify: consultation catalog tests that assert version or copy

- [ ] **Step 1: Write a failing import test**

```php
$builder->publishDefault();
$question = QuestionDefinition::where('key', 'START-01')->firstOrFail();
$this->assertSame(3, $question->versions()->max('version'));
$latest = $question->versions()->where('version', 3)->firstOrFail();
$this->assertNotEmpty($latest->help_text);
$this->assertNotEmpty($latest->why_text);
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/ConsultationCatalogTest.php`

Expected: FAIL because the configured blueprint version is still 2 and the gateway `help`/`why` fields are not imported.

- [ ] **Step 3: Import help and why and bump the blueprint version**

Pass `$item['help'] ?? null` and `$item['why'] ?? null` to `question()` for gateway questions, and set `config('consultation.blueprint.version')` to 3. Do not update locked version-2 records.

- [ ] **Step 4: Verify GREEN**

Run: `php artisan test tests/Feature/ConsultationCatalogTest.php tests/Feature/PromptVersioningTest.php tests/Feature/ConsultationMigrationTest.php`

Expected: PASS and historical version 2 remains readable.

- [ ] **Step 5: Commit versioned import**

Run: `git add config/consultation.php app/Services/Consultations/Catalog/ConsultationCatalogBuilder.php tests/Feature && git commit -m "feat: version revised consultation questions"`

### Task 5: Align Flutter question presentation

**Files:**
- Modify: `mobile/lib/features/tools/run_wizard_screen.dart`
- Modify: `mobile/lib/features/consultations/consultation_screen.dart`
- Modify: other question-facing Dart screens found by the inventory
- Modify: affected tests under `mobile/test`

- [ ] **Step 1: Update Flutter tests first**

Assert that question help precedes the answer control where applicable and that the reason uses «لماذا نسأل؟». Update expected customer copy only after seeing the tests fail.

- [ ] **Step 2: Run the focused Flutter tests and verify RED**

Run: `flutter test test/features/consultation_journey_test.dart test/widget_test.dart`

- [ ] **Step 3: Apply the approved presentation and copy**

Keep API option values and answer payloads unchanged. Change only visible strings and widget order.

- [ ] **Step 4: Verify Flutter**

Run: `flutter analyze && flutter test`

Expected: no analyzer errors and all tests pass.

- [ ] **Step 5: Commit Flutter parity**

Run: `git add mobile/lib mobile/test && git commit -m "feat: align mobile question copy"`

### Task 6: Verify, build, deploy, and smoke-test production

**Files:**
- Deploy only the changed production PHP, configuration, Blade, and built asset files.
- Never deploy `.env`, tests, documentation, keys, or local caches.

- [ ] **Step 1: Run final verification**

Run: `php artisan test && php artisan product:audit --require-verified && npm run build && git diff --check`

Run in `mobile`: `flutter analyze && flutter test`

Expected: every command exits 0.

- [ ] **Step 2: Verify the deployment manifest**

Run: `git diff --name-only 1b30d59...HEAD`

Confirm the manifest contains only this feature's source files, tests, and documentation. Exclude tests/docs/mobile source from the cPanel upload; include only server runtime files and required built public assets.

- [ ] **Step 3: Back up and deploy through the existing cPanel script**

Copy the ignored `deploy/cpanel.env` and deploy key into the isolated worktree with restricted local permissions. Build the runtime manifest with:

```powershell
$runtimeFiles = git diff --name-only 1b30d59...HEAD |
    Where-Object { $_ -notmatch '^(tests/|docs/|mobile/)' }
$runtimeFiles | Set-Content deploy/runtime-question-copy-files.txt
```

Pass the listed paths to `bash deploy/cpanel-push.sh`. The script must create its remote backup before upload and clear view/route caches afterward. The manifest itself remains local and is not uploaded.

- [ ] **Step 4: Update production catalogs without resetting data**

Run remotely: `php artisan db:seed --class=ToolCatalogSeeder --force && php artisan db:seed --class=ConsultationCatalogSeeder --force && php artisan view:clear && php artisan route:clear`.

Do not run `migrate:fresh`, truncate a table, replace `.env`, or reset user data.

- [ ] **Step 5: Smoke-test the live site**

Verify `https://khaledsaad.net/` returns 200, clean `/public` redirects still work, unauthenticated `/app` routes redirect to login, protected files remain 403, and at least one public/auth question shows the revised copy. Compare the live response with the deployed source or API response.

- [ ] **Step 6: Record deployment evidence**

Capture the deployment timestamp, deployed commit, remote backup directory, HTTP status checks, catalog version 3, and the exact live copy observed before stating completion.
