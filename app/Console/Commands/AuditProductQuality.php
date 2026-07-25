<?php

namespace App\Console\Commands;

use App\Support\ProductQuality\NeutralArabicScanner;
use App\Support\ProductQuality\ParityMatrix;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Throwable;

class AuditProductQuality extends Command
{
    protected $signature = 'product:audit
        {--matrix= : Override the product parity matrix path}
        {--require-verified : Fail unless every capability is verified}';

    protected $description = 'Validate the product parity ledger and neutral-Arabic source copy';

    public function handle(NeutralArabicScanner $scanner): int
    {
        [$records, $matrixIssues] = $this->auditMatrix();
        $apiRouteIssues = $this->auditApiRoutes($records);
        $languageIssues = $scanner->scanDefaultPaths();

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

        return $matrixIssues === [] && $apiRouteIssues === [] && $languageIssues === []
            ? self::SUCCESS
            : self::FAILURE;
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
}
