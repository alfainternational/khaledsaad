# Content Operations Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the supplied August 2026 DOCX content plan into a persistent, project-owned Arabic operations dashboard.

**Architecture:** Add `ContentPlan` and `ContentPost` records owned through the existing user/project boundary. A focused PhpWord importer normalizes the supplied document structure inside one transaction, while one controller exposes import, dashboard, edit, workflow, metrics, and archive operations through the existing authenticated panel.

**Tech Stack:** Laravel 13, Eloquent, PhpOffice PhpWord, Blade, existing CSS/JavaScript, PHPUnit feature tests, Vite.

---

### Task 1: Persist plans and posts

**Files:**
- Create: `database/migrations/2026_08_02_210000_create_content_plan_tables.php`
- Create: `app/Models/ContentPlan.php`
- Create: `app/Models/ContentPost.php`
- Modify: `app/Models/Project.php`
- Test: `tests/Feature/ContentPlanDashboardTest.php`

- [ ] **Step 1: Write the failing model test**

```php
#[Test]
public function a_plan_belongs_to_one_owned_project_and_derives_progress_from_post_steps(): void
{
    [$user, $project] = $this->project();
    $plan = ContentPlan::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'title' => 'خطة أغسطس 2026',
        'month' => '2026-08-01',
        'status' => ContentPlan::STATUS_ACTIVE,
    ]);
    $post = $plan->posts()->create([
        'position' => 1,
        'publish_at' => '2026-08-03 09:00:00',
        'pillar' => 'الموقع والأثر',
        'title' => 'التوطين يبدأ بالتأهيل',
        'x_content' => 'نص منصة X مكتمل للاختبار.',
        'linkedin_content' => 'نص لينكد إن مكتمل للاختبار.',
        'designed_at' => now(),
        'reviewed_at' => now(),
    ]);

    $this->assertTrue($project->contentPlans->contains($plan));
    $this->assertSame(50, $post->progressPercent());
}
```

- [ ] **Step 2: Run the test and verify the missing classes fail**

Run: `php artisan test --filter=ContentPlanDashboardTest`

Expected: FAIL because `ContentPlan` and its tables do not exist.

- [ ] **Step 3: Create schema and models**

Create `content_plans` with owner/project foreign keys, title, month, status, source filename, and JSON specifications. Create `content_posts` with all source text, four workflow timestamps, four performance counters, `measured_at`, and `archived_at`. Add typed casts, fillable fields, relations, `progressPercent()`, and `workflowStage()`.

- [ ] **Step 4: Run the focused test**

Run: `php artisan test --filter=ContentPlanDashboardTest`

Expected: PASS for the relationship and derived progress test.

- [ ] **Step 5: Commit the persistence slice**

```bash
git add database/migrations/2026_08_02_210000_create_content_plan_tables.php app/Models/ContentPlan.php app/Models/ContentPost.php app/Models/Project.php tests/Feature/ContentPlanDashboardTest.php
git commit -m "feat: persist operational content plans"
```

### Task 2: Parse the supplied DOCX safely

**Files:**
- Create: `app/Services/Content/ContentPlanDocxImporter.php`
- Modify: `tests/Feature/ContentPlanDashboardTest.php`

- [ ] **Step 1: Add a failing importer test using a generated DOCX fixture**

```php
#[Test]
public function it_imports_document_cards_and_plan_rules_atomically(): void
{
    [$user, $project] = $this->project();
    $path = $this->contentPlanFixture();

    $plan = app(ContentPlanDocxImporter::class)->import($path, $project, $user);

    $this->assertSame('خطة المحتوى الرقمي لشهر أغسطس 2026م', $plan->title);
    $this->assertCount(2, $plan->posts);
    $this->assertSame('التوطين يبدأ بالتأهيل', $plan->posts->first()->title);
    $this->assertSame('بطاقة نصية', $plan->posts->first()->design_brief);
    $this->assertContains('لا نصيحة علاجية', $plan->safety_rules);
}
```

The helper builds a temporary PhpWord document with the same section/table labels as the source: operational schedule, specifications, two `منشور` cards, activity protocol, and safety rules.

- [ ] **Step 2: Verify the importer test fails**

Run: `php artisan test --filter=it_imports_document_cards_and_plan_rules_atomically`

Expected: FAIL because `ContentPlanDocxImporter` does not exist.

- [ ] **Step 3: Implement the importer**

Use `IOFactory::load($path)` and recursively flatten table-cell paragraphs. Classify tables from their first cell, parse card rows by their Arabic labels, extract `Alt` and hashtags from publishing notes, normalize Arabic digits and August dates, and create the plan/posts within `DB::transaction()`. Throw `ValidationException::withMessages(['document' => '...'])` when the title or cards are missing.

- [ ] **Step 4: Add and pass rejection/rollback tests**

```php
#[Test]
public function invalid_documents_leave_no_partial_plan(): void
{
    [$user, $project] = $this->project();

    try {
        app(ContentPlanDocxImporter::class)->import($this->invalidDocxFixture(), $project, $user);
        $this->fail('The importer accepted an invalid document.');
    } catch (ValidationException) {
        $this->assertDatabaseCount('content_plans', 0);
        $this->assertDatabaseCount('content_posts', 0);
    }
}
```

Run: `php artisan test --filter=ContentPlanDashboardTest`

Expected: PASS.

- [ ] **Step 5: Commit the importer slice**

```bash
git add app/Services/Content/ContentPlanDocxImporter.php tests/Feature/ContentPlanDashboardTest.php
git commit -m "feat: import content plans from docx"
```

### Task 3: Add owned web operations

**Files:**
- Create: `app/Http/Controllers/App/ContentPlanController.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/ContentPlanDashboardTest.php`

- [ ] **Step 1: Write failing route, ownership, and validation tests**

```php
#[Test]
public function users_can_import_into_their_project_but_cannot_open_another_users_plan(): void
{
    [$user, $project] = $this->project();
    [$other, $otherProject] = $this->project('مشروع آخر');
    $foreign = ContentPlan::factoryForTest($other, $otherProject);

    $this->actingAs($user)
        ->post(route('app.content-plans.import'), [
            'project_id' => $project->id,
            'document' => UploadedFile::fake()->createWithContent('plan.docx', file_get_contents($this->contentPlanFixture())),
        ])->assertRedirect();

    $this->actingAs($user)
        ->get(route('app.content-plans.show', $foreign))
        ->assertNotFound();
}
```

- [ ] **Step 2: Verify routes fail before implementation**

Run: `php artisan test --filter=users_can_import_into_their_project`

Expected: FAIL because the named routes do not exist.

- [ ] **Step 3: Implement controller and routes**

Add named routes for index, import, show, post store/update, workflow update, metrics update, and plan/post archive. Every action calls a private ownership guard through `project.workspace.user_id` or the existing workspace membership rule, validates inputs, and redirects with a concise Arabic status.

- [ ] **Step 4: Add workflow and metrics guards**

```php
#[Test]
public function performance_cannot_be_recorded_before_either_platform_is_published(): void
{
    [$user, $project] = $this->project();
    $post = ContentPlan::factoryForTest($user, $project)->posts()->first();

    $this->actingAs($user)
        ->patch(route('app.content-posts.metrics', $post), ['x_reach' => 100])
        ->assertSessionHasErrors('metrics');
}
```

Run: `php artisan test --filter=ContentPlanDashboardTest`

Expected: PASS.

- [ ] **Step 5: Commit the operations slice**

```bash
git add app/Http/Controllers/App/ContentPlanController.php routes/web.php tests/Feature/ContentPlanDashboardTest.php
git commit -m "feat: manage content plan workflow"
```

### Task 4: Build the Arabic dashboard surfaces

**Files:**
- Create: `resources/views/app/content-plans/index.blade.php`
- Create: `resources/views/app/content-plans/show.blade.php`
- Create: `resources/views/app/content-plans/partials/post-form.blade.php`
- Create: `resources/views/app/content-plans/partials/post-card.blade.php`
- Modify: `resources/views/partials/panel-nav.blade.php`
- Modify: `tests/Feature/ContentPlanDashboardTest.php`

- [ ] **Step 1: Add failing page-content tests**

```php
#[Test]
public function the_dashboard_exposes_calendar_board_table_copy_and_rules(): void
{
    [$user, $project] = $this->project();
    $plan = ContentPlan::factoryForTest($user, $project);

    $this->actingAs($user)
        ->get(route('app.content-plans.show', $plan))
        ->assertOk()
        ->assertSee('تقويم النشر', false)
        ->assertSee('مسار التنفيذ', false)
        ->assertSee('قواعد الأمان التحريري', false)
        ->assertSee('data-copy-content', false);
}
```

- [ ] **Step 2: Verify the view test fails**

Run: `php artisan test --filter=the_dashboard_exposes_calendar_board_table_copy_and_rules`

Expected: FAIL because the views do not exist.

- [ ] **Step 3: Implement the list and dashboard views**

The index contains project selection, DOCX upload, active/archived filters, and plan cards. The show page renders real computed counters, August calendar cells, four workflow columns, a searchable table, plan specifications/rules, and accessible forms for post editing, workflow, metrics, and archive/restore.

- [ ] **Step 4: Add the global navigation entry and pass page tests**

Run: `php artisan test --filter=ContentPlanDashboardTest`

Expected: PASS.

- [ ] **Step 5: Commit the view slice**

```bash
git add resources/views/app/content-plans resources/views/partials/panel-nav.blade.php tests/Feature/ContentPlanDashboardTest.php
git commit -m "feat: add Arabic content operations dashboard"
```

### Task 5: Add responsive interaction and styling

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/js/app.js`
- Modify: `tests/Feature/ContentPlanDashboardTest.php`

- [ ] **Step 1: Add failing static contract assertions**

```php
#[Test]
public function dashboard_assets_include_view_switching_filters_and_copy_feedback(): void
{
    $js = file_get_contents(resource_path('js/app.js'));
    $css = file_get_contents(resource_path('css/app.css'));

    $this->assertStringContainsString('[data-content-view]', $js);
    $this->assertStringContainsString('[data-content-search]', $js);
    $this->assertStringContainsString('[data-copy-content]', $js);
    $this->assertStringContainsString('.content-dashboard', $css);
    $this->assertStringContainsString('@media (max-width: 760px)', $css);
}
```

- [ ] **Step 2: Verify the asset contract fails**

Run: `php artisan test --filter=dashboard_assets_include_view_switching_filters_and_copy_feedback`

Expected: FAIL on the first missing selector.

- [ ] **Step 3: Implement progressive enhancement**

Add scoped RTL dashboard CSS using existing theme tokens. Add JavaScript that switches the three views with `aria-pressed`, filters posts by normalized search text/pillar/stage, copies the selected platform text with the existing fallback selection pattern, and leaves all forms usable when JavaScript is disabled.

- [ ] **Step 4: Run feature tests and production build**

Run: `php artisan test --filter=ContentPlanDashboardTest`

Expected: PASS.

Run: `npm run build`

Expected: exit code 0 and Vite production assets emitted.

- [ ] **Step 5: Commit the interaction slice**

```bash
git add resources/css/app.css resources/js/app.js tests/Feature/ContentPlanDashboardTest.php
git commit -m "feat: add content dashboard interactions"
```

### Task 6: Verify the real document and full regression surface

**Files:**
- Modify only if verification exposes a defect in files listed above.

- [ ] **Step 1: Parse the supplied source through the importer in a rollback transaction**

Run: `php artisan content-plan:inspect "C:\Users\lenovo\Downloads\خطة المحتوى الرقمي - أغسطس 2026 - شركة الشمال التعليمية.docx"`

Expected: title found, 14 cards, design specifications, publishing specifications, activity rules, and 7 safety rules; no persistent rows created.

- [ ] **Step 2: Run the focused feature suite**

Run: `php artisan test --filter=ContentPlanDashboardTest`

Expected: all content dashboard tests pass with 0 failures.

- [ ] **Step 3: Run route and architecture regression checks**

Run: `php artisan test tests/Feature/ArchitectureBoundariesTest.php tests/Feature/WebAppJourneyTest.php tests/Feature/PanelSearchAndShareTest.php`

Expected: all selected regression tests pass with 0 failures.

- [ ] **Step 4: Build frontend assets**

Run: `npm run build`

Expected: exit code 0.

- [ ] **Step 5: Review the final diff**

Run: `git diff --check` and `git status --short`

Expected: no whitespace errors; only the content dashboard files plus pre-existing user changes are present.
