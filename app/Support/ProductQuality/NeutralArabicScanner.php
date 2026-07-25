<?php

namespace App\Support\ProductQuality;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class NeutralArabicScanner
{
    /**
     * @var array<string, string>
     */
    public const array REPLACEMENTS = [
        'دي' => 'هذه',
        'دا' => 'هذا',
        'وين' => 'أين',
        'شنو' => 'ما',
        'منو' => 'من',
        'إيش' => 'ما',
        'وش' => 'ما',
        'سوّي' => 'نفّذ',
        'سوي' => 'نفّذ',
        'كده' => 'هكذا',
        'دلوقتي' => 'الآن',
        'مش' => 'ليس',
        'عشان' => 'لكي',
        'اللي' => 'الذي',
        'خلّيه' => 'فعّل',
        'خليه' => 'فعّل',
        'ما في' => 'لا يوجد',
    ];

    /**
     * @param  list<string>  $paths
     * @return list<array{file: string, line: int, term: string, replacement: string, text: string}>
     */
    public function scan(array $paths): array
    {
        $issues = [];

        foreach ($this->files($paths) as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);

            if ($lines === false) {
                continue;
            }

            foreach ($lines as $index => $line) {
                if ($this->isCommentOnly($line)) {
                    continue;
                }

                foreach (self::REPLACEMENTS as $term => $replacement) {
                    if (! $this->containsTerm($line, $term)) {
                        continue;
                    }

                    $issues[] = [
                        'file' => $file,
                        'line' => $index + 1,
                        'term' => $term,
                        'replacement' => $replacement,
                        'text' => trim($line),
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<array{file: string, line: int, term: string, replacement: string, text: string}>
     */
    public function scanDefaultPaths(): array
    {
        $root = dirname(__DIR__, 3);

        return $this->scan([
            $root.DIRECTORY_SEPARATOR.'app',
            $root.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'tools',
            $root.DIRECTORY_SEPARATOR.'mobile'.DIRECTORY_SEPARATOR.'lib',
            $root.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'views',
        ]);
    }

    private function containsTerm(string $line, string $term): bool
    {
        $quoted = preg_quote($term, '/');
        $pattern = '/(?<![\p{Arabic}\p{L}\p{M}])'.$quoted.'(?![\p{Arabic}\p{L}\p{M}])/u';

        return preg_match($pattern, $line) === 1;
    }

    private function isCommentOnly(string $line): bool
    {
        $trimmed = ltrim($line);

        foreach (['//', '#', '/*', '*', '*/', '{{--', '--}}', '<!--', '-->'] as $prefix) {
            if (str_starts_with($trimmed, $prefix)) {
                return true;
            }
        }

        return $trimmed === '';
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function files(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile() || ! in_array($file->getExtension(), ['php', 'dart'], true)) {
                    continue;
                }

                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }
}
