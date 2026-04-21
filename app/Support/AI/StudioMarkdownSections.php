<?php

namespace App\Support\AI;

/**
 * Splits studio AI output on Markdown ## headings for UI and export.
 *
 * @phpstan-type Section array{title: string, body: string}
 */
final class StudioMarkdownSections
{
    /**
     * @return list<Section>
     */
    public static function split(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $lines = explode("\n", $text);
        $sections = [];
        $currentTitle = null;
        /** @var list<string> $buffer */
        $buffer = [];

        $flush = function () use (&$sections, &$currentTitle, &$buffer): void {
            $body = trim(implode("\n", $buffer));
            if ($currentTitle === null && $body === '') {
                return;
            }
            $sections[] = [
                'title' => $currentTitle ?? '',
                'body' => $body,
            ];
            $currentTitle = null;
            $buffer = [];
        };

        foreach ($lines as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $m)) {
                $flush();
                $currentTitle = trim($m[1]);
                $buffer = [];
            } else {
                $buffer[] = $line;
            }
        }
        $flush();

        return $sections !== [] ? $sections : [['title' => '', 'body' => trim($text)]];
    }
}
