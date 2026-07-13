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
        }
    }
}
