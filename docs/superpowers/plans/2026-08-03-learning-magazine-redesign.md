# Learning Magazine Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publish the 20 Word marketing lessons as one editable, search-ready learning magazine while preserving every source word and the existing `/blog/1` record.

**Architecture:** Extend the existing `Content` domain with source and learning metadata, generate a versioned data package from the Word files, and import it idempotently into the current admin-managed content table. Render the same record through an enhanced public page with structured blocks, navigation, schema.org data, and generated editorial covers.

**Tech Stack:** Laravel 13, PHP 8.3, PHPUnit 12, PhpWord, Blade, Vite, vanilla JavaScript, CSS, built-in ImageGen.

---

### Task 1: Source metadata contract

**Files:**
- Create: `database/migrations/2026_08_03_200000_add_learning_metadata_to_contents_table.php`
- Modify: `app/Models/Content.php`
- Modify: `app/Http/Requests/Admin/ContentRequest.php`
- Test: `tests/Feature/MarketingCourseImportTest.php`

- [ ] **Step 1: Write the failing schema test**

```php
public function test_content_exposes_source_and_learning_metadata(): void
{
    $content = Content::factory()->create([
        'source_key' => 'marketing-course-01',
        'source_text_hash' => hash('sha256', 'نص'),
        'learning_order' => 1,
        'learning_meta' => ['outline' => [['id' => 'start', 'title' => 'البداية']]],
    ]);

    $this->assertSame(1, $content->learning_order);
    $this->assertSame('البداية', $content->learning_meta['outline'][0]['title']);
}
```

- [ ] **Step 2: Run the test and confirm missing-column failure**

Run: `php artisan test tests/Feature/MarketingCourseImportTest.php --filter=source_and_learning_metadata`
Expected: FAIL because the metadata columns do not exist.

- [ ] **Step 3: Add nullable indexed metadata fields and model/request casts**

```php
$table->string('source_key')->nullable()->unique();
$table->string('source_filename')->nullable();
$table->char('source_text_hash', 64)->nullable();
$table->unsignedSmallInteger('learning_order')->nullable()->index();
$table->json('learning_meta')->nullable();
```

- [ ] **Step 4: Re-run the focused test**

Run: `php artisan test tests/Feature/MarketingCourseImportTest.php --filter=source_and_learning_metadata`
Expected: PASS.

### Task 2: Deterministic Word extraction package

**Files:**
- Create: `scripts/extract-marketing-course.php`
- Create: `database/data/content/marketing-course/manifest.php`
- Create: `database/data/content/marketing-course/lessons/01.php` through `20.php`
- Test: `tests/Unit/MarketingCoursePackageTest.php`

- [ ] **Step 1: Write the failing package integrity tests**

```php
public function test_package_contains_twenty_ordered_lessons_with_valid_hashes(): void
{
    $manifest = require database_path('data/content/marketing-course/manifest.php');
    $this->assertCount(20, $manifest['lessons']);
    $this->assertSame(range(1, 20), array_column($manifest['lessons'], 'order'));
    foreach ($manifest['lessons'] as $lesson) {
        $data = require database_path($lesson['path']);
        $this->assertSame($data['source_text_hash'], hash('sha256', $data['source_text']));
    }
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Unit/MarketingCoursePackageTest.php`
Expected: FAIL because the package does not exist.

- [ ] **Step 3: Implement extraction with PhpWord**

The script reads non-lock `.docx` files in numeric order, preserves paragraph and table text exactly, maps existing phrases to semantic blocks without rewriting them, and writes PHP arrays containing `source_text`, `source_text_hash`, `body_html`, `body_json`, `learning_meta`, SEO support copy, and image prompt metadata.

- [ ] **Step 4: Generate and audit the package**

Run: `php scripts/extract-marketing-course.php "D:\دورة تعلم التسويق"`
Expected: `20 lessons generated; 0 text mismatches; 0 temporary files included`.

- [ ] **Step 5: Re-run package tests**

Run: `php artisan test tests/Unit/MarketingCoursePackageTest.php`
Expected: PASS.

### Task 3: Idempotent importer and course series

**Files:**
- Create: `app/Services/Content/MarketingCourseImporter.php`
- Create: `app/Console/Commands/ImportMarketingCourse.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/MarketingCourseImportTest.php`

- [ ] **Step 1: Write failing importer tests**

```php
public function test_import_updates_existing_first_article_and_is_idempotent(): void
{
    $existing = Content::factory()->create(['slug' => '1', 'title' => 'قديم']);
    $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
    $this->assertSame($existing->id, Content::where('source_key', 'marketing-course-01')->value('id'));
    $this->assertDatabaseCount('contents', 20);
    $this->artisan('content:import-marketing-course', ['--publish' => true])->assertSuccessful();
    $this->assertDatabaseCount('contents', 20);
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/MarketingCourseImportTest.php --filter=import_updates`
Expected: FAIL because the command is missing.

- [ ] **Step 3: Implement transactional upsert**

Use `DB::transaction()`, find lesson 1 by `source_key` or slug `1`, use stable descriptive slugs for 2–20, create/resolve the `تعلم التسويق` category, preserve existing IDs, and clear the sitemap cache after success.

- [ ] **Step 4: Verify the importer**

Run: `php artisan test tests/Feature/MarketingCourseImportTest.php`
Expected: PASS with 20 records and unchanged first ID.

### Task 4: Hybrid reader experience

**Files:**
- Modify: `app/Http/Controllers/Site/ContentLibraryController.php`
- Create: `app/Support/Content/LearningPresenter.php`
- Modify: `resources/views/site/content/show.blade.php`
- Create: `resources/views/site/content/_learning-outline.blade.php`
- Create: `resources/views/site/content/_learning-navigation.blade.php`
- Modify: `resources/css/content-library.css`
- Create: `resources/js/content-learning.js`
- Modify: `resources/js/app.js`
- Test: `tests/Feature/LearningMagazineExperienceTest.php`

- [ ] **Step 1: Write failing page contract tests**

```php
public function test_lesson_renders_outline_progress_tools_and_adjacent_navigation(): void
{
    [$first, $second] = $this->seedMarketingLessons(2);
    $this->get(route('content.show', $first))
        ->assertOk()
        ->assertSee('data-reading-progress', false)
        ->assertSee('في هذا الدرس')
        ->assertSee(route('content.show', $second), false)
        ->assertSee('مهمة تطبيقية قبل الدردشة القادمة:');
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/LearningMagazineExperienceTest.php`
Expected: FAIL for missing learning UI.

- [ ] **Step 3: Implement presenter, Blade components, responsive CSS and local progress JS**

The presenter derives outline, previous/next records, reading time, and block classes from stored metadata. JavaScript updates the top progress bar, active outline item, copy-link control, and `localStorage` completion without changing article text.

- [ ] **Step 4: Verify page behavior**

Run: `php artisan test tests/Feature/LearningMagazineExperienceTest.php tests/Feature/PublicContentLibraryTest.php`
Expected: PASS.

### Task 5: SEO, AEO and machine-readable discovery

**Files:**
- Create: `app/Support/Content/ContentStructuredData.php`
- Modify: `resources/views/layouts/public.blade.php`
- Modify: `resources/views/site/content/show.blade.php`
- Modify: `routes/web.php`
- Create: `resources/views/site/content/llms.blade.php`
- Test: `tests/Feature/ContentDiscoverabilityTest.php`

- [ ] **Step 1: Write failing structured-data tests**

```php
public function test_lesson_emits_parseable_article_learning_and_breadcrumb_schema(): void
{
    $content = $this->publishedMarketingLesson();
    $response = $this->get(route('content.show', $content))->assertOk();
    $schema = $this->extractJsonLd($response->getContent());
    $types = collect($schema['@graph'])->pluck('@type')->flatten();
    $this->assertContains('Article', $types);
    $this->assertContains('LearningResource', $types);
    $this->assertContains('BreadcrumbList', $types);
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/ContentDiscoverabilityTest.php`
Expected: FAIL because content-specific graph and llms route are missing.

- [ ] **Step 3: Implement safe JSON-LD graph and metadata sections**

Render JSON through `json_encode` outside Blade directives, set article OG type, large Twitter card, image dimensions, `isPartOf`, `position`, and FAQ only when source questions exist. Add `/llms.txt` with published learning URLs.

- [ ] **Step 4: Verify discovery surfaces**

Run: `php artisan test tests/Feature/ContentDiscoverabilityTest.php tests/Feature/ProductionRootRewriteTest.php`
Expected: PASS.

### Task 6: Editorial cover system

**Files:**
- Create: `public/assets/content/marketing-course/source/lesson-01.png` through `lesson-20.png`
- Create: `scripts/build-content-covers.php`
- Create: generated WebP/PNG derivatives under `public/assets/content/marketing-course/`
- Test: `tests/Unit/MarketingCourseCoverTest.php`

- [ ] **Step 1: Write failing cover manifest tests**

```php
public function test_every_lesson_has_hero_card_and_open_graph_images(): void
{
    foreach (range(1, 20) as $order) {
        $stem = sprintf('lesson-%02d', $order);
        $this->assertFileExists(public_path("assets/content/marketing-course/{$stem}-hero.webp"));
        $this->assertFileExists(public_path("assets/content/marketing-course/{$stem}-card.webp"));
        $this->assertFileExists(public_path("assets/content/marketing-course/{$stem}-og.png"));
    }
}
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Unit/MarketingCourseCoverTest.php`
Expected: FAIL because covers are missing.

- [ ] **Step 3: Generate 20 symbolic editorial illustrations with built-in ImageGen**

Each prompt uses the approved blue/orange editorial system, a topic-specific metaphor, landscape composition, and constraints `no text, no letters, no logo, no watermark`.

- [ ] **Step 4: Build deterministic derivatives and bind paths to lesson package**

Run: `php scripts/build-content-covers.php`
Expected: `60 derivatives written; 20 source images validated`.

- [ ] **Step 5: Verify cover assets**

Run: `php artisan test tests/Unit/MarketingCourseCoverTest.php`
Expected: PASS.

### Task 7: Build, import, deploy and verify production

**Files:**
- Modify: `docs/architecture/RELEASE-2026-08-03-LEARNING-MAGAZINE.md`

- [ ] **Step 1: Run focused backend tests and formatter**

Run: `php artisan test tests/Unit/MarketingCoursePackageTest.php tests/Unit/MarketingCourseCoverTest.php tests/Feature/MarketingCourseImportTest.php tests/Feature/LearningMagazineExperienceTest.php tests/Feature/ContentDiscoverabilityTest.php tests/Feature/PublicContentLibraryTest.php tests/Feature/AdminContentManagementTest.php`
Expected: all pass.

- [ ] **Step 2: Build production assets**

Run: `npm run build`
Expected: Vite exits 0 and writes production assets.

- [ ] **Step 3: Commit and push the release branch**

Run: `git add ... && git commit -m "feat(content): publish marketing learning magazine" && git push -u origin codex/learning-magazine-redesign`
Expected: clean branch exists on origin.

- [ ] **Step 4: Back up production database and deploy without replacing `.env` or `APP_KEY`**

Run the hosting deployment mechanism, then `php artisan migrate --force`, `php artisan content:import-marketing-course --publish --force`, `php artisan optimize:clear`, and `php artisan optimize`.

- [ ] **Step 5: Verify live production**

Check `/blog`, `/blog/1`, all 19 new canonical URLs, `/sitemap.xml`, `/robots.txt`, `/llms.txt`, images, JSON-LD parsing, previous/next navigation, mobile layout, and HTTP/error logs. Record exact URLs and response evidence in the release document.
