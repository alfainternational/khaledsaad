# Parity and Neutral Arabic Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create a deterministic product-parity ledger and neutral-Arabic quality gate, then correct all current dialect-bound user-facing copy.

**Architecture:** A JSON-compatible YAML document stores capability records without adding a YAML dependency. Focused PHP readers validate the ledger and scan selected user-facing source trees line by line while ignoring source comments. An Artisan command runs both checks locally and in deployment gates.

**Tech Stack:** PHP 8.3, Laravel 13, PHPUnit 12, Blade, Dart source scanning.

---

### Task 1: Add the parity matrix reader and schema validation

**Files:**
- Create: `docs/product/parity-matrix.yaml`
- Create: `app/Support/ProductQuality/ParityMatrix.php`
- Create: `tests/Unit/ProductQuality/ParityMatrixTest.php`

- [ ] **Step 1: Write the failing reader test**

```php
#[Test]
public function every_capability_has_unique_complete_evidence_fields(): void
{
    $records = app(ParityMatrix::class)->records();

    $this->assertNotEmpty($records);
    $this->assertSameSize($records, array_unique(array_column($records, 'id')));

    foreach ($records as $record) {
        $this->assertContains($record['role'], ['visitor', 'customer', 'admin']);
        $this->assertContains($record['status'], ['missing', 'implemented', 'verified']);
        $this->assertArrayHasKey('web', $record);
        $this->assertArrayHasKey('api', $record);
        $this->assertArrayHasKey('mobile', $record);
        $this->assertArrayHasKey('states', $record);
        $this->assertArrayHasKey('tests', $record);
    }
}
```

- [ ] **Step 2: Run RED**

Run:

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\ProductQuality\ParityMatrixTest.php
```

Expected: failure because `App\Support\ProductQuality\ParityMatrix` does not exist.

- [ ] **Step 3: Implement the reader**

```php
final class ParityMatrix
{
    public function __construct(
        private readonly string $path = '',
    ) {}

    public function records(): array
    {
        $path = $this->path !== '' ? $this->path : base_path('docs/product/parity-matrix.yaml');
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $decoded['capabilities'];
    }
}
```

- [ ] **Step 4: Add complete current-state records**

Use one record per capability:

```json
{
  "id": "visitor.home.view",
  "role": "visitor",
  "web": {"route": "home", "method": "GET"},
  "api": {"route": null, "method": null},
  "mobile": {"screen": null, "action": null},
  "states": ["loading", "error", "success"],
  "tests": {"web": ["Tests\\\\Feature\\\\PublicHomePageTest"], "api": [], "mobile": []},
  "status": "implemented"
}
```

Populate visitor, customer, and administrator records from `routes/web.php`, `routes/api.php`, and current Flutter screens. Use `missing` when a required equivalent is absent; never use `verified` without all three applicable evidence arrays.

- [ ] **Step 5: Run GREEN**

Run the focused test and expect all assertions to pass.

- [ ] **Step 6: Commit**

Commit only the matrix, reader, and test.

### Task 2: Add the neutral-Arabic scanner

**Files:**
- Create: `docs/product/arabic-style-guide.md`
- Create: `app/Support/ProductQuality/NeutralArabicScanner.php`
- Create: `tests/Unit/ProductQuality/NeutralArabicScannerTest.php`

- [ ] **Step 1: Write the failing scanner test**

```php
#[Test]
public function it_reports_dialect_bound_copy_with_file_and_line(): void
{
    $path = tempnam(sys_get_temp_dir(), 'copy-');
    file_put_contents($path, "<h1>الصفحة دي ما موجودة عندنا.</h1>\n");

    $issues = (new NeutralArabicScanner)->scan([$path]);

    $this->assertSame('دي', $issues[0]['term']);
    $this->assertSame(1, $issues[0]['line']);
}
```

- [ ] **Step 2: Run RED**

Expected: failure because the scanner does not exist.

- [ ] **Step 3: Implement exact patterns and comment skipping**

The scanner must:

- scan `resources/views`, `mobile/lib`, user-visible tool definitions, notifications, and report services;
- include `.php`, `.blade.php`, and `.dart`;
- ignore blank lines and source-comment-only lines;
- return `file`, `line`, `term`, and `replacement`;
- match the approved list: `دي`, `دا`, `وين`, `شنو`, `منو`, `إيش`, `وش`, `سوّي`, `كده`, `دلوقتي`, `مش`, `عشان`, `اللي`, `خلّيه`, `ما في`.

- [ ] **Step 4: Add the style guide**

Document the fixed voice, address form, glossary, prohibited terms, replacements, punctuation, numbers, buttons, errors, and empty states.

- [ ] **Step 5: Run GREEN**

Run the scanner unit test and expect it to pass.

- [ ] **Step 6: Commit**

Commit the scanner, guide, and test.

### Task 3: Correct current user-visible language

**Files:**
- Modify: files returned by `NeutralArabicScanner::scanDefaultPaths()`.
- Modify: `app/Services/Tools/PipelineSchemas.php`
- Modify: affected expectations in Laravel and Flutter tests.

- [ ] **Step 1: Add a failing repository-copy test**

```php
#[Test]
public function repository_user_facing_copy_uses_neutral_arabic(): void
{
    $issues = (new NeutralArabicScanner)->scanDefaultPaths();

    $this->assertSame([], $issues, json_encode($issues, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
```

- [ ] **Step 2: Run RED**

Expected: findings such as `الصفحة دي`, `وين وصل مشروعك؟`, `خلّيه حيًّا`, and `مش توقّع`.

- [ ] **Step 3: Replace every finding contextually**

Required conversions include:

```text
الصفحة دي ما موجودة عندنا. -> هذه الصفحة غير موجودة.
وين وصل مشروعك؟ -> إلى أي مرحلة وصل مشروعك؟
خلّيه حيًّا -> فعّل المتابعة المستمرة.
مش توقّع -> وليس توقعًا.
اللي ياخذ عملاءك -> المنافس الذي يجذب عملاءك.
عشان -> لكي / حتى / لأن، according to sentence meaning.
```

Update `PipelineSchemas::systemPreamble()` so generated reports obey the same neutral-Arabic restriction and no longer explicitly allow dialect forms.

- [ ] **Step 4: Run GREEN and regressions**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\ProductQuality\NeutralArabicScannerTest.php tests\Feature\PublicHomePageTest.php tests\Feature\ToolRunPipelineTest.php
Push-Location mobile
flutter test --no-pub
Pop-Location
```

Expected: all pass and the repository-copy assertion reports zero issues.

- [ ] **Step 5: Commit**

Commit only intentional copy, preamble, and expectation changes.

### Task 4: Add the product audit command

**Files:**
- Create: `app/Console/Commands/AuditProductQuality.php`
- Create: `tests/Feature/ProductQualityCommandTest.php`

- [ ] **Step 1: Write the failing command test**

```php
#[Test]
public function product_audit_passes_only_when_matrix_and_language_are_valid(): void
{
    $this->artisan('product:audit')
        ->expectsOutputToContain('Parity matrix')
        ->expectsOutputToContain('Neutral Arabic')
        ->assertSuccessful();
}
```

- [ ] **Step 2: Run RED**

Expected: command-not-defined failure.

- [ ] **Step 3: Implement the command**

The command validates schema, duplicate IDs, allowed states, verified evidence, and neutral copy. Print counts and return `Command::FAILURE` on any issue.

- [ ] **Step 4: Run GREEN**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Feature\ProductQualityCommandTest.php
php artisan product:audit
```

Expected: exit code 0 with matrix and language summaries.

- [ ] **Step 5: Run foundation gate**

```powershell
php vendor\phpunit\phpunit\phpunit --colors=never tests\Unit\ProductQuality tests\Feature\ProductQualityCommandTest.php
vendor\bin\pint --test
```

Expected: all checks pass.

- [ ] **Step 6: Commit**

Commit command and test.
