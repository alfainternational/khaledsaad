<?php

namespace App\Domain\AI\Worker;

use InvalidArgumentException;

final class DocumentExtractionContract
{
    public const VERSION = 'v2';

    public const LOCATOR_TYPES = [
        'page',
        'image_region',
        'docx_paragraph',
        'docx_table',
        'xlsx_cell',
        'xlsx_row',
        'xlsx_table',
    ];

    /** @return array<string, mixed> */
    public static function definition(): array
    {
        return [
            'version' => self::VERSION,
            'max_chunks' => 100,
            'max_text_chars' => (int) config('services.knowledge.upload_max_text_chars', 350000),
            'max_chunk_chars' => (int) config('services.knowledge.upload_chunk_chars', 3500),
            'locator_types' => self::LOCATOR_TYPES,
        ];
    }

    /** @param array<string, mixed> $result */
    public static function validateResult(array $result): void
    {
        $definition = self::definition();
        $text = $result['text'] ?? null;
        $chunks = $result['chunks'] ?? null;
        if (($result['contract_version'] ?? null) !== self::VERSION
            || ! is_string($text) || trim($text) === ''
            || mb_strlen($text) > $definition['max_text_chars']
            || ! is_array($chunks) || ! array_is_list($chunks)
            || $chunks === [] || count($chunks) > $definition['max_chunks']) {
            throw new InvalidArgumentException('The structured extraction result violates the v2 contract.');
        }

        foreach ($chunks as $chunk) {
            $content = is_array($chunk) ? ($chunk['content'] ?? null) : null;
            $locator = is_array($chunk) ? ($chunk['locator'] ?? null) : null;
            if (! is_string($content) || trim($content) === ''
                || mb_strlen($content) > $definition['max_chunk_chars']
                || ! is_array($locator)
                || ! in_array($locator['type'] ?? null, self::LOCATOR_TYPES, true)) {
                throw new InvalidArgumentException('A structured extraction chunk or locator is invalid.');
            }
            self::validateLocator($locator);
        }
    }

    /** @param array<string, mixed> $locator */
    private static function validateLocator(array $locator): void
    {
        if (self::containsForbiddenKey($locator)) {
            throw new InvalidArgumentException('Structured extraction locators contain a forbidden key.');
        }

        $positiveInt = static fn (mixed $value): bool => is_int($value) && $value > 0;
        $valid = match ($locator['type']) {
            'page' => $positiveInt($locator['page'] ?? null)
                && ($locator['page'] <= 500)
                && in_array($locator['method'] ?? 'text', ['text', 'ocr'], true),
            'image_region' => self::validImageRegion($locator),
            'docx_paragraph' => $positiveInt($locator['paragraph'] ?? null),
            'docx_table' => $positiveInt($locator['table'] ?? null)
                && $positiveInt($locator['row'] ?? null),
            'xlsx_cell' => is_string($locator['sheet'] ?? null)
                && preg_match('/\A[A-Z]{1,3}[1-9][0-9]{0,6}\z/D', (string) ($locator['cell'] ?? '')) === 1,
            'xlsx_row', 'xlsx_table' => self::validSpreadsheetRange($locator),
            default => false,
        };
        if (! $valid) {
            throw new InvalidArgumentException('A structured extraction locator is outside its allowed bounds.');
        }
    }

    /** @param array<string, mixed> $locator */
    private static function validImageRegion(array $locator): bool
    {
        $bbox = $locator['bbox'] ?? null;
        $confidence = $locator['confidence'] ?? null;
        if (! is_array($bbox) || ! array_is_list($bbox) || count($bbox) !== 4
            || ! is_numeric($confidence) || $confidence < 0 || $confidence > 100) {
            return false;
        }
        $values = array_map('floatval', $bbox);

        return collect($values)->every(fn (float $value): bool => $value >= 0 && $value <= 1)
            && $values[0] < $values[2] && $values[1] < $values[3]
            && (! isset($locator['page']) || (is_int($locator['page']) && $locator['page'] > 0 && $locator['page'] <= 500));
    }

    /** @param array<string, mixed> $locator */
    private static function validSpreadsheetRange(array $locator): bool
    {
        if (! is_string($locator['sheet'] ?? null) || trim($locator['sheet']) === ''
            || mb_strlen($locator['sheet']) > 100
            || ! is_int($locator['row'] ?? null) || $locator['row'] < 1 || $locator['row'] > 1_048_576) {
            return false;
        }
        foreach ((array) ($locator['cells'] ?? []) as $cell) {
            if (! is_string($cell) || preg_match('/\A[A-Z]{1,3}[1-9][0-9]{0,6}\z/D', $cell) !== 1) {
                return false;
            }
        }

        return true;
    }

    private static function containsForbiddenKey(array $value): bool
    {
        foreach ($value as $key => $item) {
            if (in_array(strtolower((string) $key), ['path', 'disk', 'secret', 'token', 'password'], true)
                || (is_array($item) && self::containsForbiddenKey($item))) {
                return true;
            }
        }

        return false;
    }
}
