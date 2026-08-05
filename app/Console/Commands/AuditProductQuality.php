<?php

namespace App\Console\Commands;

use App\Models\ConsultationBlueprint;
use App\Models\ConsultationEvent;
use App\Models\QuestionVersion;
use App\Models\ToolField;
use App\Modules\Intake\AnswerTypeRegistry;
use App\Modules\Intake\Catalog\ConsultationCatalogValidator;
use App\Support\ProductQuality\NeutralArabicScanner;
use App\Support\ProductQuality\ParityMatrix;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuditProductQuality extends Command
{
    protected $signature = 'product:audit
        {--matrix= : Override the product parity matrix path}
        {--require-verified : Fail unless every capability is verified}
        {--require-consultation : Fail unless the consultation catalog is installed and valid}
        {--require-formats : Fail unless all stored form types have web and mobile widgets}
        {--mobile-source= : Override the Flutter consultation source directory}
        {--prompt-fixtures= : Override the Prompt v2 fixture catalog path}
        {--require-prompt-fixtures : Fail when the Prompt v2 fixture catalog is not deployed}';

    protected $description = 'Validate the product parity ledger and neutral-Arabic source copy';

    public function handle(NeutralArabicScanner $scanner): int
    {
        [$records, $matrixIssues] = $this->auditMatrix();
        $apiRouteIssues = $this->auditApiRoutes($records);
        $languageIssues = $scanner->scanDefaultPaths();
        $promptFixturePath = $this->option('prompt-fixtures') ?: base_path('tests/Fixtures/prompt-v2/catalog.php');
        $promptFixtureAvailable = is_file($promptFixturePath);
        $promptFixtureIssues = $promptFixtureAvailable || $this->option('require-prompt-fixtures')
            ? $this->auditPromptFixtures($promptFixturePath)
            : [];
        $consultationIssues = $this->auditConsultation();
        $formatIssues = $this->option('require-formats') ? $this->auditFormats() : [];

        if ($matrixIssues === []) {
            $counts = array_count_values(array_column($records, 'status'));
            $this->info(sprintf(
                'Parity matrix: PASS (%d capabilities; %d verified, %d implemented, %d missing)',
                count($records),
                $counts['verified'] ?? 0,
                $counts['implemented'] ?? 0,
                $counts['missing'] ?? 0,
            ));
        } else {
            $this->error(sprintf('Parity matrix: FAIL (%d issues)', count($matrixIssues)));

            foreach ($matrixIssues as $issue) {
                $this->line(" - {$issue}");
            }
        }

        if ($apiRouteIssues === []) {
            $declared = count(array_filter(
                $records,
                fn (array $record) => is_string($record['api']['route'] ?? null)
                    && $record['api']['route'] !== '',
            ));
            $this->info("API route coverage: PASS ({$declared} declared routes)");
        } else {
            $this->error(sprintf('API route coverage: FAIL (%d issues)', count($apiRouteIssues)));

            foreach ($apiRouteIssues as $issue) {
                $this->line(" - {$issue}");
            }
        }

        if ($languageIssues === []) {
            $this->info('Neutral Arabic: PASS (0 issues)');
        } else {
            $this->error(sprintf('Neutral Arabic: FAIL (%d issues)', count($languageIssues)));

            foreach ($languageIssues as $issue) {
                $this->line(sprintf(
                    ' - %s:%d [%s -> %s]',
                    $issue['file'],
                    $issue['line'],
                    $issue['term'],
                    $issue['replacement'],
                ));
            }
        }

        if (! $promptFixtureAvailable && ! $this->option('require-prompt-fixtures')) {
            $this->warn('Prompt v2 evaluation: SKIP (fixture catalog is not deployed)');
        } elseif ($promptFixtureIssues === []) {
            $this->info('Prompt v2 evaluation: PASS (88 fixtures; 11 tools)');
        } else {
            $this->error(sprintf('Prompt v2 evaluation: FAIL (%d issues)', count($promptFixtureIssues)));

            foreach ($promptFixtureIssues as $issue) {
                $this->line(" - {$issue}");
            }
        }

        if ($consultationIssues === []) {
            $this->info('Consultation integrity: PASS (catalog and event privacy)');
        } else {
            $this->error(sprintf('Consultation integrity: FAIL (%d issues)', count($consultationIssues)));
            foreach ($consultationIssues as $issue) {
                $this->line(" - {$issue}");
            }
        }

        if ($this->option('require-formats')) {
            if ($formatIssues === []) {
                $this->info('Form answer formats: PASS (catalog, validator, Blade, and Flutter)');
            } else {
                $this->error(sprintf('Form answer formats: FAIL (%d issues)', count($formatIssues)));
                foreach ($formatIssues as $issue) {
                    $this->line(" - {$issue}");
                }
            }
        }

        return $matrixIssues === [] && $apiRouteIssues === [] && $languageIssues === [] && $promptFixtureIssues === [] && $consultationIssues === [] && $formatIssues === []
            ? self::SUCCESS
            : self::FAILURE;
    }

    /** @return list<string> */
    private function auditFormats(): array
    {
        $issues = [];
        $bladePath = resource_path('views/app/consultations/_answer-field.blade.php');
        $mobileSource = $this->option('mobile-source') ?: base_path('mobile/lib/features/consultations');
        $flutterPaths = [
            $mobileSource.DIRECTORY_SEPARATOR.'consultation_screen.dart',
            $mobileSource.DIRECTORY_SEPARATOR.'models.dart',
        ];

        if (! is_file($bladePath)) {
            $issues[] = "Blade form source is missing: {$bladePath}";
        }

        foreach ($flutterPaths as $path) {
            if (! is_file($path)) {
                $issues[] = "Flutter form source is missing: {$path}";
            }
        }

        if ($issues !== []) {
            return $issues;
        }

        $supported = AnswerTypeRegistry::all();
        $stored = QuestionVersion::query()->distinct()->pluck('answer_type')->all();
        $toolTypes = ToolField::query()->distinct()->pluck('type')->all();

        foreach (array_unique([...$stored, ...$toolTypes]) as $type) {
            if (! in_array($type, $supported, true)) {
                $issues[] = "unsupported stored answer type: {$type}";
            }
        }

        $blade = file_get_contents($bladePath) ?: '';
        $flutter = (file_get_contents($flutterPaths[0]) ?: '')
            .(file_get_contents($flutterPaths[1]) ?: '');
        foreach ($supported as $type) {
            $bladeCovered = in_array($type, ['textarea'], true) || str_contains($blade, "'{$type}'");
            $flutterCovered = in_array($type, ['text', 'textarea', 'url', 'email', 'date'], true)
                || str_contains($flutter, "'{$type}'");
            if (! $bladeCovered) {
                $issues[] = "Blade widget missing for {$type}";
            }
            if (! $flutterCovered) {
                $issues[] = "Flutter widget missing for {$type}";
            }
        }

        return $issues;
    }

    /** @return list<string> */
    private function auditConsultation(): array
    {
        $issues = [];
        if (count(config('consultation.modules', [])) !== 19 || count(config('consultation.gateway_questions', [])) !== 12) {
            $issues[] = 'consultation config must define 19 modules and 12 gateway questions';
        }
        if (! Schema::hasTable('consultation_blueprints')) {
            if ($this->option('require-consultation')) {
                $issues[] = 'consultation tables are not installed';
            }

            return $issues;
        }

        $blueprint = ConsultationBlueprint::where('key', 'smart-marketing-consultation')->first();
        if ($blueprint?->currentVersion === null) {
            $issues[] = 'published consultation blueprint is missing';
        } else {
            try {
                app(ConsultationCatalogValidator::class)->validate($blueprint->currentVersion);
            } catch (Throwable $exception) {
                $issues[] = 'catalog validation failed: '.$exception->getMessage();
            }
        }

        if (Schema::hasTable('consultation_events')) {
            foreach (ConsultationEvent::query()->select(['id', 'metadata'])->cursor() as $event) {
                if ($this->containsSensitiveEventMetadata($event->metadata ?? [])) {
                    $issues[] = "consultation event {$event->id} contains sensitive metadata";
                }
            }
        }

        return array_values(array_unique($issues));
    }

    /** @param array<string,mixed> $metadata */
    private function containsSensitiveEventMetadata(array $metadata): bool
    {
        $forbidden = ['answer', 'value', 'content', 'email', 'phone', 'payment', 'secret', 'token', 'file_text'];
        foreach ($metadata as $key => $value) {
            if (in_array(strtolower((string) $key), $forbidden, true)) {
                return true;
            }
            if (is_array($value) && $this->containsSensitiveEventMetadata($value)) {
                return true;
            }
            if (is_string($value)) {
                if (Str::isUuid($value)) {
                    continue;
                }

                if (preg_match('/[^\s@]+@[^\s@]+\.[^\s@]+|\b(?:\+?\d[\s-]?){8,}\b/u', $value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    private function auditMatrix(): array
    {
        try {
            $records = (new ParityMatrix($this->option('matrix') ?: null))->records();
        } catch (Throwable $exception) {
            return [[], ['matrix cannot be read: '.$exception->getMessage()]];
        }

        $issues = [];
        $ids = [];

        if ($records === []) {
            $issues[] = 'capabilities must not be empty';
        }

        foreach ($records as $index => $record) {
            $label = is_string($record['id'] ?? null) ? $record['id'] : "#{$index}";

            foreach (['id', 'role', 'status', 'web', 'api', 'mobile', 'states', 'tests'] as $key) {
                if (! array_key_exists($key, $record)) {
                    $issues[] = "{$label} is missing {$key}";
                }
            }

            if (! is_string($record['id'] ?? null) || trim($record['id']) === '') {
                $issues[] = "{$label} has an invalid id";
            } elseif (isset($ids[$record['id']])) {
                $issues[] = "{$label} is duplicated";
            } else {
                $ids[$record['id']] = true;
            }

            if (! in_array($record['role'] ?? null, ParityMatrix::ROLES, true)) {
                $issues[] = "{$label} has an invalid role";
            }

            if (! in_array($record['status'] ?? null, ParityMatrix::STATUSES, true)) {
                $issues[] = "{$label} has an invalid status";
            }

            foreach (['web', 'api', 'mobile', 'states', 'tests'] as $key) {
                if (isset($record[$key]) && ! is_array($record[$key])) {
                    $issues[] = "{$label}.{$key} must be an array";
                }
            }

            if (isset($record['states']) && is_array($record['states']) && $record['states'] === []) {
                $issues[] = "{$label}.states must not be empty";
            }

            if (($record['status'] ?? null) !== 'verified') {
                continue;
            }

            foreach (['web', 'api', 'mobile'] as $surface) {
                if (($record[$surface]['applicable'] ?? true) === false) {
                    continue;
                }

                if (empty($record['tests'][$surface])) {
                    $issues[] = "{$label} is verified without {$surface} evidence";
                }
            }
        }

        if ($this->option('require-verified')) {
            foreach ($records as $record) {
                if (($record['status'] ?? null) !== 'verified') {
                    $issues[] = ($record['id'] ?? 'unknown').' is not verified';
                }
            }
        }

        return [$records, array_values(array_unique($issues))];
    }

    /**
     * @param  list<array<string, mixed>>  $records
     * @return list<string>
     */
    private function auditApiRoutes(array $records): array
    {
        $issues = [];

        foreach ($records as $record) {
            if (($record['api']['applicable'] ?? false) !== true) {
                continue;
            }

            $name = $record['api']['route'] ?? null;

            if ($name === null || $name === '') {
                continue;
            }

            if (! is_string($name) || ! Route::has($name)) {
                $issues[] = ($record['id'] ?? 'unknown').': missing route '.(is_scalar($name) ? $name : '[invalid]');
            }
        }

        return $issues;
    }

    /** @return list<string> */
    private function auditPromptFixtures(string $path): array
    {
        if (! is_file($path)) {
            return ['prompt fixture catalog is missing'];
        }

        $catalog = require $path;
        if (! is_array($catalog) || count($catalog) !== 11) {
            return ['prompt fixture catalog must cover exactly 11 tools'];
        }

        $issues = [];
        $ids = [];
        foreach ($catalog as $tool => $fixtures) {
            if (! is_array($fixtures) || count($fixtures) < 8) {
                $issues[] = "{$tool} must have at least 8 fixtures";

                continue;
            }

            foreach ($fixtures as $fixture) {
                $id = is_array($fixture) ? ($fixture['id'] ?? null) : null;
                if (! is_string($id) || $id === '' || isset($ids[$id])) {
                    $issues[] = "{$tool} has a missing or duplicate fixture id";
                } else {
                    $ids[$id] = true;
                }
            }
        }

        if (count($ids) < 88) {
            $issues[] = 'prompt fixture catalog must contain at least 88 unique cases';
        }

        return array_values(array_unique($issues));
    }
}
