<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;
use UnexpectedValueException;

class ImportLegacyKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:import-legacy';

    protected $description = 'Import legacy JSON memories into the structured knowledge store';

    public function handle(StructuredKnowledgeRepository $repository): int
    {
        try {
            return $this->importFiles($repository);
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Legacy knowledge import failed.');

            return self::FAILURE;
        }
    }

    private function importFiles(StructuredKnowledgeRepository $repository): int
    {
        $files = Storage::disk('local')->files('ai-knowledge');
        sort($files, SORT_STRING);

        $imported = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'json') {
                continue;
            }

            try {
                $memory = $this->readMemory($file);
                $lines = $this->flatten($memory['data']);

                if ($lines === []) {
                    throw new UnexpectedValueException('Legacy memory contains no scalar data.');
                }
            } catch (JsonException|UnexpectedValueException) {
                $skipped++;

                continue;
            }

            $content = implode("\n", $lines);
            $uri = 'legacy://'.$memory['key'];
            $scope = KnowledgeScope::global();
            $current = $repository->latestDocument($scope, 'legacy_memory', $uri);

            $repository->storeDocument(
                $scope,
                'legacy_memory',
                $uri,
                $memory['key'],
                $content,
                [['heading' => $memory['key'], 'content' => $content, 'locator' => ['canonical_uri' => $uri]]],
                50,
            );

            if ($current !== null && $current->content === $content) {
                $unchanged++;
            } else {
                $imported++;
            }
        }

        $this->line("Imported: {$imported}; unchanged: {$unchanged}; skipped: {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @return array{key: string, data: array<mixed>, learned_at: string}
     */
    private function readMemory(string $file): array
    {
        $raw = Storage::disk('local')->get($file);
        $memory = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($memory)
            || ! isset($memory['key'], $memory['learned_at'])
            || ! is_string($memory['key'])
            || trim($memory['key']) === ''
            || ! is_string($memory['learned_at'])
            || ! array_key_exists('data', $memory)
            || ! is_array($memory['data'])) {
            throw new UnexpectedValueException('Legacy memory schema is invalid.');
        }

        $learnedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $memory['learned_at']);

        // The structured schema has no source timestamp column yet. Validate the exact
        // format emitted by KnowledgeStore without overloading document timestamps.
        if ($learnedAt === false || $learnedAt->format(DateTimeInterface::ATOM) !== $memory['learned_at']) {
            throw new UnexpectedValueException('Legacy learned_at must use ISO-8601 ATOM format.');
        }

        $memory['key'] = trim($memory['key']);

        return $memory;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        if (! array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        $lines = [];

        foreach ($data as $key => $value) {
            $segment = $this->escapePathSegment((string) $key);
            $path = $prefix === '' ? $segment : $prefix.'.'.$segment;

            if (is_array($value)) {
                $lines = array_merge($lines, $this->flatten($value, $path));
            } elseif (is_scalar($value) || $value === null) {
                $lines[] = $path.': '.$this->serializeScalar($value);
            }
        }

        return $lines;
    }

    private function escapePathSegment(string $segment): string
    {
        $segment = str_replace('~', '~0', $segment);
        $segment = str_replace('.', '~1', $segment);

        return preg_replace_callback(
            '/[\x00-\x1F\x7F]/',
            fn (array $match): string => sprintf('~u%04X', ord($match[0])),
            $segment,
        ) ?? $segment;
    }

    private function serializeScalar(mixed $value): string
    {
        return json_encode(
            $value,
            JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        );
    }
}
