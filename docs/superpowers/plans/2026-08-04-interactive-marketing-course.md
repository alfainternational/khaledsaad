# Interactive Marketing Course Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert all applied work in the 20-lesson marketing series into one project-aware execution path with reusable answers, AI grading, practical outputs, and production deployment.

**Architecture:** A version-controlled course catalog defines lessons, exercises, questions, rubrics, and outputs. Additive Laravel models store one project run, mutable drafts, and immutable review history. Deterministic services validate completeness and choose the next exercise; a queued structured AI evaluator grades every input and the overall result, then records only the user's confirmed answers in the existing project Brain.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, MySQL/SQLite tests, Laravel queues, existing StructuredRunner and Brain modules, PHPUnit/Pest, Vite, cPanel SSH deployment.

---

### Task 1: Course catalog and validation

**Files:**
- Create: `database/data/learning/marketing-course.php`
- Create: `app/Modules/Learning/MarketingCourseCatalog.php`
- Create: `tests/Unit/Modules/Learning/MarketingCourseCatalogTest.php`

- [ ] **Step 1: Write the failing catalog test**

```php
<?php

namespace Tests\Unit\Modules\Learning;

use App\Modules\Learning\MarketingCourseCatalog;
use Tests\TestCase;

class MarketingCourseCatalogTest extends TestCase
{
    public function test_the_catalog_contains_all_twenty_lessons_and_forty_two_exercises(): void
    {
        $catalog = app(MarketingCourseCatalog::class);

        $this->assertCount(20, $catalog->lessons());
        $this->assertCount(42, $catalog->exercises());
        $this->assertSame(range(1, 20), array_column($catalog->lessons(), 'number'));
    }

    public function test_every_exercise_has_a_stable_unique_key_questions_and_a_deliverable(): void
    {
        $exercises = app(MarketingCourseCatalog::class)->exercises();
        $keys = array_column($exercises, 'key');

        $this->assertCount(count($keys), array_unique($keys));

        foreach ($exercises as $exercise) {
            $this->assertNotEmpty($exercise['title']);
            $this->assertNotEmpty($exercise['purpose']);
            $this->assertNotEmpty($exercise['deliverable']);
            $this->assertNotEmpty($exercise['questions']);

            foreach ($exercise['questions'] as $question) {
                $this->assertNotEmpty($question['key']);
                $this->assertNotEmpty($question['label']);
                $this->assertNotEmpty($question['rubric']);
            }
        }
    }
}
```

- [ ] **Step 2: Run the test and verify the module is missing**

Run: `php artisan test tests/Unit/Modules/Learning/MarketingCourseCatalogTest.php --stop-on-failure`

Expected: FAIL because `MarketingCourseCatalog` does not exist.

- [ ] **Step 3: Create the catalog reader**

```php
<?php

namespace App\Modules\Learning;

use Illuminate\Support\Collection;
use InvalidArgumentException;

class MarketingCourseCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $catalog = null;

    public function lessons(): array
    {
        return $this->load()['lessons'];
    }

    public function exercises(): array
    {
        return collect($this->lessons())->flatMap(fn (array $lesson) => $lesson['exercises'])->values()->all();
    }

    public function exercise(string $key): array
    {
        return collect($this->exercises())->firstWhere('key', $key)
            ?? throw new InvalidArgumentException("Unknown marketing exercise: {$key}");
    }

    public function lessonFor(string $exerciseKey): array
    {
        return collect($this->lessons())->first(
            fn (array $lesson) => collect($lesson['exercises'])->contains('key', $exerciseKey)
        ) ?? throw new InvalidArgumentException("Unknown marketing exercise: {$exerciseKey}");
    }

    private function load(): array
    {
        return $this->catalog ??= require database_path('data/learning/marketing-course.php');
    }
}
```

- [ ] **Step 4: Add the 20 lessons and 42 exercise definitions**

The catalog must use version `1` and these exact keys, grouped by lesson:

```php
1 => ['marketing-reality-check', 'first-market-reading'],
2 => ['define-target-market'],
3 => ['describe-real-customer', 'customer-clarity-check'],
4 => ['customer-research-14-days', 'customer-research-log'],
5 => ['core-marketing-message', 'buyable-offer', 'sales-growth-system'],
6 => ['rewrite-sales-content'],
7 => ['choose-marketing-channel', 'content-distribution-plan'],
8 => ['weekly-content-calendar'],
9 => ['build-marketing-identity', 'identity-clarity-check'],
10 => ['design-selling-post'],
11 => ['design-paid-ad'],
12 => ['increase-sales-system'],
13 => ['measure-current-performance'],
14 => ['customer-loyalty-review'],
15 => ['ai-marketing-strategy'],
16 => ['crisis-response-simulation'],
17 => ['b2b-marketing-strategy'],
18 => ['seo-strategy'],
19 => ['influencer-campaign-plan'],
20 => [
    'workshop-swot', 'workshop-current-performance', 'workshop-ideal-customer',
    'workshop-core-message', 'workshop-irresistible-offer', 'workshop-brand-identity',
    'workshop-channel-selection', 'workshop-90-day-content', 'workshop-first-content',
    'workshop-paid-ads', 'workshop-loyalty', 'workshop-measurement',
    'workshop-emergency-plan', 'workshop-90-day-schedule', 'workshop-resources-budget',
    'workshop-numeric-goals',
],
```

Every exercise definition must include `key`, `title`, `purpose`, `deliverable`, `duration_minutes`, `source_url`, `brain_dependencies`, and two to six questions. Every question must include `key`, `label`, `help`, `example`, `type`, `required`, `min`, `rubric`, and optional `brain_key`. Copy must tell a marketer what to write and what they receive; it must not mention software internals.

- [ ] **Step 5: Run catalog tests**

Run: `php artisan test tests/Unit/Modules/Learning/MarketingCourseCatalogTest.php`

Expected: PASS, 20 lessons and 42 exercises.

- [ ] **Step 6: Commit**

```bash
git add database/data/learning/marketing-course.php app/Modules/Learning/MarketingCourseCatalog.php tests/Unit/Modules/Learning/MarketingCourseCatalogTest.php
git commit -m "feat(learning): define the applied marketing course"
```

### Task 2: Persistence model and project ownership

**Files:**
- Create: `database/migrations/2026_08_04_100000_create_marketing_learning_tables.php`
- Create: `app/Models/MarketingLearningRun.php`
- Create: `app/Models/MarketingExerciseAttempt.php`
- Create: `app/Models/MarketingExerciseReview.php`
- Create: `database/factories/MarketingLearningRunFactory.php`
- Create: `database/factories/MarketingExerciseAttemptFactory.php`
- Modify: `app/Models/Project.php`
- Create: `tests/Feature/MarketingLearningPersistenceTest.php`

- [ ] **Step 1: Write failing persistence tests**

```php
public function test_a_project_has_one_reusable_active_learning_run(): void
{
    $project = Project::factory()->create();
    $first = MarketingLearningRun::startFor($project, $project->workspace->users()->first());
    $second = MarketingLearningRun::startFor($project, $project->workspace->users()->first());

    $this->assertTrue($first->is($second));
}

public function test_an_attempt_keeps_answers_and_reviews_are_immutable_history(): void
{
    $attempt = MarketingExerciseAttempt::factory()->create(['answers' => ['problem' => 'وصف واضح للمشكلة']]);
    $attempt->reviews()->create([
        'revision' => 1,
        'answers' => $attempt->answers,
        'completeness_score' => 100,
        'ai_score' => 80,
        'final_score' => 86,
        'feedback' => ['summary' => 'إجابة جيدة'],
        'catalog_version' => 1,
        'reviewed_at' => now(),
    ]);

    $this->assertSame(86, $attempt->reviews()->first()->final_score);
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/MarketingLearningPersistenceTest.php --stop-on-failure`

Expected: FAIL because the tables and models do not exist.

- [ ] **Step 3: Add additive tables and model casts/relations**

The migration creates:

```php
Schema::create('marketing_learning_runs', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('project_id')->constrained()->cascadeOnDelete();
    $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('status', 20)->default('active');
    $table->string('current_exercise_key')->nullable();
    $table->unsignedSmallInteger('completed_exercises')->default(0);
    $table->unsignedTinyInteger('average_score')->nullable();
    $table->timestamp('started_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    $table->timestamps();
    $table->unique(['project_id', 'status']);
});
```

Create `marketing_exercise_attempts` with run FK, exercise key, revision, answers JSON, status, completeness/AI/final scores, feedback JSON, failure reason text, submitted/evaluated timestamps, and unique run+exercise. Create `marketing_exercise_reviews` with attempt FK, revision, immutable answer/feedback JSON snapshots, three scores, catalog version, reviewed timestamp, and unique attempt+revision.

- [ ] **Step 4: Run persistence tests and the nearest project tests**

Run: `php artisan test tests/Feature/MarketingLearningPersistenceTest.php tests/Feature/WebAppJourneyTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_04_100000_create_marketing_learning_tables.php app/Models/MarketingLearningRun.php app/Models/MarketingExerciseAttempt.php app/Models/MarketingExerciseReview.php app/Models/Project.php tests/Feature/MarketingLearningPersistenceTest.php
git commit -m "feat(learning): persist project course progress"
```

### Task 3: Completeness scoring, prefills, and adaptive recommendation

**Files:**
- Create: `app/Modules/Learning/MarketingExerciseCompletenessScorer.php`
- Create: `app/Modules/Learning/MarketingLearningRecommender.php`
- Create: `app/Modules/Learning/MarketingAnswerPrefill.php`
- Create: `tests/Unit/Modules/Learning/MarketingLearningServicesTest.php`

- [ ] **Step 1: Write failing service tests**

```php
public function test_completeness_requires_meaningful_text_and_valid_numbers(): void
{
    $exercise = ['questions' => [
        ['key' => 'problem', 'type' => 'textarea', 'required' => true, 'min' => 20],
        ['key' => 'budget', 'type' => 'number', 'required' => true, 'min' => 0],
    ]];

    $result = app(MarketingExerciseCompletenessScorer::class)->score($exercise, [
        'problem' => 'قصير', 'budget' => -1,
    ]);

    $this->assertLessThan(60, $result['score']);
    $this->assertSame(['problem', 'budget'], $result['missing']);
}

public function test_recommender_prioritizes_missing_audience_knowledge(): void
{
    $project = Project::factory()->create();
    $run = MarketingLearningRun::factory()->for($project)->create();

    $recommendation = app(MarketingLearningRecommender::class)->next($run);

    $this->assertSame('describe-real-customer', $recommendation['exercise']['key']);
    $this->assertStringContainsString('عميل', $recommendation['reason']);
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Unit/Modules/Learning/MarketingLearningServicesTest.php --stop-on-failure`

Expected: FAIL because the services do not exist.

- [ ] **Step 3: Implement deterministic services**

`MarketingExerciseCompletenessScorer::score()` returns `score`, `missing`, and `answered`. `MarketingAnswerPrefill::forQuestion()` uses this priority: current draft, latest completed attempt with the same `brain_key`, then active Brain fact. `MarketingLearningRecommender::next()` checks missing Brain keys in this order: audience, value proposition, goal, channels, tracking, content, retention; skips completed exercises; then returns the first eligible catalog exercise and a human reason.

- [ ] **Step 4: Run service tests**

Run: `php artisan test tests/Unit/Modules/Learning/MarketingLearningServicesTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Learning tests/Unit/Modules/Learning/MarketingLearningServicesTest.php
git commit -m "feat(learning): recommend and validate course work"
```

### Task 4: Structured AI review, history, and Brain recording

**Files:**
- Create: `app/Modules/Learning/MarketingExerciseEvaluationSchema.php`
- Create: `app/Modules/Learning/MarketingExerciseEvaluator.php`
- Create: `app/Jobs/EvaluateMarketingExercise.php`
- Create: `tests/Feature/MarketingExerciseEvaluationTest.php`

- [ ] **Step 1: Write failing evaluator tests**

```php
public function test_evaluator_grades_every_input_and_stores_a_final_review(): void
{
    $this->fakeStructuredRunner([
        'input_feedback' => [
            ['key' => 'customer', 'score' => 80, 'comment' => 'محدد', 'suggestion' => 'أضف وقت الحاجة'],
            ['key' => 'problem', 'score' => 70, 'comment' => 'واضح', 'suggestion' => 'اذكر ما جربه العميل'],
        ],
        'overall_score' => 75,
        'strengths' => ['العميل محدد'],
        'improvements' => ['أضف سلوك الشراء'],
        'next_action' => 'تحدث مع عميلين هذا الأسبوع',
        'deliverable' => 'وصف عملي للعميل المستهدف',
    ]);

    $attempt = MarketingExerciseAttempt::factory()->readyForReview()->create([
        'completeness_score' => 90,
    ]);

    app(MarketingExerciseEvaluator::class)->evaluate($attempt);

    $this->assertSame(80, $attempt->refresh()->final_score);
    $this->assertCount(1, $attempt->reviews);
    $this->assertCount(2, $attempt->feedback['input_feedback']);
}

public function test_provider_failure_preserves_answers_and_allows_retry_without_a_fake_final_score(): void
{
    $this->fakeStructuredRunnerFailure();
    $attempt = MarketingExerciseAttempt::factory()->readyForReview()->create();

    app(MarketingExerciseEvaluator::class)->evaluate($attempt);

    $this->assertSame('review_failed', $attempt->refresh()->status);
    $this->assertNull($attempt->final_score);
    $this->assertNotEmpty($attempt->answers);
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/MarketingExerciseEvaluationTest.php --stop-on-failure`

Expected: FAIL because the evaluator does not exist.

- [ ] **Step 3: Implement schema and evaluator**

The schema requires this exact shape:

```php
[
    'input_feedback' => [['key' => 'string', 'score' => 'integer 0..100', 'comment' => 'string', 'suggestion' => 'string']],
    'overall_score' => 'integer 0..100',
    'strengths' => ['string'],
    'improvements' => ['string'],
    'next_action' => 'string',
    'deliverable' => 'string',
]
```

The evaluator locks the attempt, refuses duplicate evaluating/completed work, marks evaluating, calls `StructuredRunner` with Arabic system instructions, verifies feedback keys match submitted questions, computes `round(completeness * .30 + overall * .70)`, creates a review revision, updates the attempt, records only user answers carrying a `brain_key` through `BrainWriter` with `EvidenceLevel::Inferred`, and refreshes run progress. User claims are not independent measurements. Catch provider/invalid-output exceptions, store a shortened failure reason, and never erase answers or the latest successful review.

- [ ] **Step 4: Implement the queued job and retry safety**

`EvaluateMarketingExercise` accepts the attempt ID, uses `ShouldQueue`, `SerializesModels`, a 3-attempt limit, and unique locking by attempt ID while evaluating.

- [ ] **Step 5: Run evaluation and Brain tests**

Run: `php artisan test tests/Feature/MarketingExerciseEvaluationTest.php tests/Feature/BrainLedgerTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Learning app/Jobs/EvaluateMarketingExercise.php tests/Feature/MarketingExerciseEvaluationTest.php
git commit -m "feat(learning): review marketing work with AI"
```

### Task 5: Web journey, authorization, and copy

**Files:**
- Create: `app/Http/Controllers/App/MarketingLearningController.php`
- Create: `resources/views/app/learning/marketing/index.blade.php`
- Create: `resources/views/app/learning/marketing/exercise.blade.php`
- Create: `resources/views/app/learning/marketing/result.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/views/partials/panel-nav.blade.php`
- Modify: `resources/css/app.css`
- Create: `tests/Feature/MarketingLearningJourneyTest.php`

- [ ] **Step 1: Write failing route and journey tests**

```php
public function test_owner_sees_the_recommended_exercise_and_all_twenty_lessons(): void
{
    [$user, $project] = $this->userWithProject();

    $this->actingAs($user)
        ->get(route('app.learning.marketing.index', $project))
        ->assertOk()
        ->assertSee('ابدأ من هنا')
        ->assertSee('الدرس 20');
}

public function test_saving_a_step_resumes_without_losing_the_answer(): void
{
    [$user, $project] = $this->userWithProject();

    $this->actingAs($user)->put(route('app.learning.marketing.save', [$project, 'describe-real-customer']), [
        'step' => 1,
        'answer' => 'صاحب متجر صغير ينشر باستمرار ولا تصله طلبات كافية',
    ])->assertRedirect();

    $this->actingAs($user)
        ->get(route('app.learning.marketing.exercise', [$project, 'describe-real-customer', 'step' => 2]))
        ->assertOk();
}

public function test_a_stranger_cannot_open_or_update_another_projects_course(): void
{
    [$owner, $project] = $this->userWithProject();
    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('app.learning.marketing.index', $project))
        ->assertForbidden();
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/MarketingLearningJourneyTest.php --stop-on-failure`

Expected: FAIL because routes and controller do not exist.

- [ ] **Step 3: Add protected routes**

```php
Route::get('projects/{project}/learn/marketing', [MarketingLearningController::class, 'index'])->name('learning.marketing.index');
Route::get('projects/{project}/learn/marketing/{exercise}', [MarketingLearningController::class, 'exercise'])->name('learning.marketing.exercise');
Route::put('projects/{project}/learn/marketing/{exercise}', [MarketingLearningController::class, 'save'])->name('learning.marketing.save');
Route::post('projects/{project}/learn/marketing/{exercise}/review', [MarketingLearningController::class, 'submit'])->middleware('throttle:20,60')->name('learning.marketing.submit');
Route::get('projects/{project}/learn/marketing/{exercise}/result', [MarketingLearningController::class, 'result'])->name('learning.marketing.result');
Route::post('projects/{project}/learn/marketing/{exercise}/retry', [MarketingLearningController::class, 'retry'])->middleware('throttle:10,60')->name('learning.marketing.retry');
```

- [ ] **Step 4: Implement controller and Blade screens**

The controller uses `ResolvesWorkspace`, validates exercise keys through the catalog, validates each answer from its question definition, stores drafts, refuses incomplete submission, dispatches the job once, and returns the three screens in the approved journey. Blade copy must include purpose, duration, deliverable, one question per page, optional example, progress, per-input feedback, overall score, improvement actions, and next exercise. Escape user/AI text with Blade `{{ }}`.

- [ ] **Step 5: Add sidebar discovery and responsive CSS**

Add the user link text `تطبيق الدروس` and active-route state `app.learning.*`. CSS must support narrow screens, a visible progress bar, 44px controls, focus states, result-score ring, lesson accordion, and dark mode through existing variables.

- [ ] **Step 6: Run journey and neutral-copy tests**

Run: `php artisan test tests/Feature/MarketingLearningJourneyTest.php tests/Feature/UserFacingQuestionCopyTest.php tests/Unit/ProductQuality/NeutralArabicScannerTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/App/MarketingLearningController.php resources/views/app/learning resources/views/partials/panel-nav.blade.php resources/css/app.css routes/web.php tests/Feature/MarketingLearningJourneyTest.php
git commit -m "feat(learning): add the applied marketing journey"
```

### Task 6: Product integration and failure-state coverage

**Files:**
- Modify: `app/Http/Controllers/App/ProjectController.php`
- Modify: `resources/views/app/projects/show.blade.php`
- Modify: `resources/views/app/dashboard.blade.php`
- Create: `tests/Feature/MarketingLearningIntegrationTest.php`

- [ ] **Step 1: Write failing integration tests**

```php
public function test_project_page_explains_the_next_marketing_action_and_output(): void
{
    [$user, $project] = $this->userWithProject();

    $this->actingAs($user)->get(route('app.projects.show', $project))
        ->assertOk()
        ->assertSee('خطوتك التسويقية التالية')
        ->assertSee('ستحصل على');
}

public function test_failed_review_page_keeps_answers_and_offers_plain_language_retry(): void
{
    [$user, $project, $attempt] = $this->failedAttempt();

    $this->actingAs($user)->get(route('app.learning.marketing.result', [$project, $attempt->exercise_key]))
        ->assertOk()
        ->assertSee('حفظنا إجاباتك')
        ->assertSee('أعد المراجعة')
        ->assertDontSee('API')
        ->assertDontSee('model');
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/MarketingLearningIntegrationTest.php --stop-on-failure`

Expected: FAIL because project/dashboard integration is absent.

- [ ] **Step 3: Add the next-action cards and all user states**

Project and dashboard cards must link to the same run and show the recommender reason. Cover empty, draft, queued/evaluating, completed, and review-failed states. Do not create a second run or duplicate project context.

- [ ] **Step 4: Run integration tests**

Run: `php artisan test tests/Feature/MarketingLearningIntegrationTest.php tests/Feature/WebAppJourneyTest.php tests/Feature/TaskExecutionGuideTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/App/ProjectController.php resources/views/app/projects/show.blade.php resources/views/app/dashboard.blade.php tests/Feature/MarketingLearningIntegrationTest.php
git commit -m "feat(learning): surface the next marketing action"
```

### Task 7: Full verification and release build

**Files:**
- Modify only files required by failures found in this task.

- [ ] **Step 1: Run focused learning suite**

Run:

```bash
php artisan test \
  tests/Unit/Modules/Learning \
  tests/Feature/MarketingLearningPersistenceTest.php \
  tests/Feature/MarketingExerciseEvaluationTest.php \
  tests/Feature/MarketingLearningJourneyTest.php \
  tests/Feature/MarketingLearningIntegrationTest.php
```

Expected: PASS, zero failures.

- [ ] **Step 2: Run adjacent platform suite**

Run:

```bash
php artisan test \
  tests/Feature/BrainLedgerTest.php \
  tests/Feature/TaskExecutionGuideTest.php \
  tests/Feature/WebAppJourneyTest.php \
  tests/Feature/UserFacingQuestionCopyTest.php \
  tests/Unit/ProductQuality/NeutralArabicScannerTest.php
```

Expected: PASS, zero failures.

- [ ] **Step 3: Run formatting and class-case gates**

Run:

```bash
vendor/bin/pint --test
php deploy/check-class-case.php
```

Expected: both exit 0.

- [ ] **Step 4: Build production assets**

Run: `npm run build`

Expected: Vite exits 0 and creates hashed files under `public/build`.

- [ ] **Step 5: Inspect diff and commit release candidate**

Run:

```bash
git status --short
git diff --check
git diff --stat HEAD~6..HEAD
```

Expected: no secrets, `.env`, deployment keys, caches, vendor files, or tests intended for upload.

Commit any verification-only fix with `git commit -m "fix(learning): satisfy release verification"`.

### Task 8: Merge, deploy, migrate, and verify production

**Files:**
- Runtime files changed in Tasks 1-6
- Built assets under `public/build`
- Migration `database/migrations/2026_08_04_100000_create_marketing_learning_tables.php`

- [ ] **Step 1: Rebase or merge the verified branch into the main workspace without discarding user changes**

Run `git status --short` in both worktree and main workspace. Merge `codex/interactive-marketing-course` into the current main-workspace branch with a normal non-destructive merge.

- [ ] **Step 2: Prepare deployment credentials inside the worktree if absent**

Copy the ignored `deploy/cpanel.env` and available ignored deployment key from the main workspace to the same ignored paths in the worktree. Do not print their contents and do not stage them.

- [ ] **Step 3: Deploy runtime files and assets with backup and migration**

Run from the verified source tree:

```bash
bash deploy/cpanel-push.sh --build --migrate \
  app/Http/Controllers/App/MarketingLearningController.php \
  app/Jobs/EvaluateMarketingExercise.php \
  app/Models/MarketingLearningRun.php \
  app/Models/MarketingExerciseAttempt.php \
  app/Models/MarketingExerciseReview.php \
  app/Models/Project.php \
  app/Modules/Learning \
  database/data/learning/marketing-course.php \
  database/migrations/2026_08_04_100000_create_marketing_learning_tables.php \
  resources/views/app/learning \
  resources/views/app/projects/show.blade.php \
  resources/views/app/dashboard.blade.php \
  resources/views/partials/panel-nav.blade.php \
  resources/css/app.css \
  routes/web.php
```

Expected: remote backup path printed, every file marked `UP`, migration succeeds, caches clear, and the script prints the production URL.

- [ ] **Step 4: Verify production routes and pages**

Run HTTP checks for:

```text
https://khaledsaad.net/                         => 200
https://khaledsaad.net/login                    => 200
https://khaledsaad.net/app                      => 302 to login when signed out
https://khaledsaad.net/app/projects/example/learn/marketing => 302 to login when signed out
```

Use SSH to run `php artisan route:list --name=app.learning.marketing` and verify six routes, `php artisan migrate:status` and verify the new migration is Ran, and `php artisan queue:restart`.

- [ ] **Step 5: Record exact release evidence**

Record deployed commit, remote backup directory, migration status, build hash, HTTP statuses, route count, and production timestamp. Only then report completion to the user.
