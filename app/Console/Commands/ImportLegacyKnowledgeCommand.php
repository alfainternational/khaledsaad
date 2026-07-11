<?php

namespace App\Console\Commands;

use App\Domain\AI\Knowledge\KnowledgeScope;
use App\Domain\AI\Knowledge\StructuredKnowledgeRepository;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class ImportLegacyKnowledgeCommand extends Command
{
    protected $signature = 'knowledge:import-legacy';

    protected $description = 'Import legacy JSON memories into the structured knowledge store';

    public function handle(StructuredKnowledgeRepository $repository): int
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
                    throw new JsonException('Legacy memory contains no scalar data.');
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
                    [['heading' => $memory['key'], 'content' => $content, 'locator' => ['file' => $file]]],
                    50,
                );

                if ($current !== null && $current->content === $content) {
                    $unchanged++;
                } else {
                    $imported++;
                }
            } catch (Throwable) {
                $skipped++;
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
            throw new JsonException('Legacy memory schema is invalid.');
        }

        new DateTimeImmutable($memory['learned_at']);
        $memory['key'] = trim($memory['key']);

        return $memory;
    }

    /**
     * @param  array<mixed>  $data
     * @return list<string>
     */
    private function flatten(array $data, string $prefix = ''): array
    {
        ksort($data, SORT_STRING);
        $lines = [];

        foreach ($data as $key => $value) {
            $path = ltrim($prefix.'.'.$key, '.');

            if (is_array($value)) {
                $lines = array_merge($lines, $this->flatten($value, $path));
            } elseif (is_scalar($value) || $value === null) {
                $lines[] = $path.': '.($value === null ? 'null' : (string) $value);
            }
        }

        return $lines;
    }
}
