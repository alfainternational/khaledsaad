<?php

namespace App\Domain\AI\Knowledge\Uploads;

use JsonException;

class TextKnowledgeExtractor
{
    private const SUPPORTED_MIMES = [
        'text/plain' => 'text',
        'text/markdown' => 'markdown',
        'text/csv' => 'csv',
        'application/csv' => 'csv',
        'application/json' => 'json',
        'text/json' => 'json',
        'text/html' => 'html',
        'application/xhtml+xml' => 'html',
    ];

    public function extract(string $path, string $mimeType, string $originalName): ExtractedKnowledge
    {
        $format = self::SUPPORTED_MIMES[strtolower(trim(explode(';', $mimeType)[0]))] ?? null;
        if ($format === null) {
            throw new KnowledgeExtractionException('unsupported_mime', 'The uploaded file type is not supported.');
        }

        $maxBytes = (int) config('services.knowledge.upload_max_bytes', 2 * 1024 * 1024);
        $bytes = @filesize($path);
        if ($bytes === false || $bytes < 1 || $bytes > $maxBytes) {
            throw new KnowledgeExtractionException('invalid_size', 'The uploaded file is empty or exceeds the extraction limit.');
        }

        $raw = file_get_contents($path);
        if (! is_string($raw) || str_contains($raw, "\0")) {
            throw new KnowledgeExtractionException('binary_content', 'Binary content cannot be indexed as text.');
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            throw new KnowledgeExtractionException('invalid_encoding', 'The uploaded text must use UTF-8 encoding.');
        }

        $normalized = trim(str_replace(["\r\n", "\r"], "\n", $raw));
        [$content, $chunks] = match ($format) {
            'json' => $this->json($normalized),
            'csv' => $this->csv($normalized),
            'html' => $this->text($this->html($normalized)),
            default => $this->text($normalized),
        };

        if ($content === '') {
            throw new KnowledgeExtractionException('empty_text', 'No indexable text was found in the uploaded file.');
        }

        return new ExtractedKnowledge(
            content: $content,
            chunks: $chunks,
            language: preg_match('/\p{Arabic}/u', $content) === 1 ? 'ar' : 'und',
            metadata: [
                'format' => $format,
                'original_name' => $originalName,
                'text_bytes' => strlen($content),
                'chunk_count' => count($chunks),
            ],
        );
    }

    /** @return array{string, list<array{heading: string|null, content: string, locator: array<string, mixed>}>} */
    private function text(string $text): array
    {
        $lines = explode("\n", $text);
        $chunks = [];
        $buffer = [];
        $start = 1;
        $maxChars = (int) config('services.knowledge.upload_chunk_chars', 3500);

        $flush = function (int $end) use (&$chunks, &$buffer, &$start): void {
            $content = trim(implode("\n", $buffer));
            if ($content !== '') {
                $chunks[] = [
                    'heading' => null,
                    'content' => $content,
                    'locator' => ['line_start' => $start, 'line_end' => $end],
                ];
            }
            $buffer = [];
        };

        foreach ($lines as $index => $line) {
            if ($buffer !== [] && mb_strlen(implode("\n", [...$buffer, $line])) > $maxChars) {
                $flush($index);
                $start = $index + 1;
            }
            $buffer[] = $line;
        }
        $flush(count($lines));

        return [trim($text), array_slice($chunks, 0, 100)];
    }

    /** @return array{string, list<array{heading: string|null, content: string, locator: array<string, mixed>}>} */
    private function json(string $text): array
    {
        try {
            $data = json_decode($text, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new KnowledgeExtractionException('invalid_json', 'The JSON document is malformed.');
        }

        $lines = [];
        $this->flattenJson($data, '', $lines);

        return $this->text(implode("\n", $lines));
    }

    /** @param list<string> $lines */
    private function flattenJson(mixed $value, string $path, array &$lines): void
    {
        if (! is_array($value)) {
            $rendered = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                $value === null => 'null',
                default => (string) $value,
            };
            $lines[] = ($path !== '' ? $path : 'value').': '.$rendered;

            return;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            $child = $path === '' ? (string) $key : $path.'.'.$key;
            $this->flattenJson($item, $child, $lines);
        }
    }

    /** @return array{string, list<array{heading: string|null, content: string, locator: array<string, mixed>}>} */
    private function csv(string $text): array
    {
        $lines = [];
        foreach (explode("\n", $text) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map(fn (string $cell): string => trim($cell), str_getcsv($line));
            $lines[] = 'row '.($index + 1).': '.implode(' | ', $cells);
        }

        return $this->text(implode("\n", $lines));
    }

    private function html(string $html): string
    {
        $html = preg_replace('/<(script|style|noscript|template)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\t ]+|\n{3,}/u', ' ', $text) ?? '');
    }
}
