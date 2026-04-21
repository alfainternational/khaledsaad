<?php

namespace App\Support\AI;

final class StudioContentRenderer
{
    public static function render(string $text): string
    {
        $text = trim(str_replace("\r\n", "\n", $text));

        if ($text === '') {
            return '<p class="studio-rich-empty">لا يوجد محتوى متاح.</p>';
        }

        $lines = explode("\n", $text);
        $html = [];
        $count = count($lines);
        $index = 0;

        while ($index < $count) {
            $line = trim($lines[$index]);

            if ($line === '') {
                $index++;

                continue;
            }

            if (preg_match('/^###\s+(.+)$/u', $line, $matches)) {
                $html[] = '<h4 class="studio-rich-subheading">'.self::inline($matches[1]).'</h4>';
                $index++;

                continue;
            }

            if (self::isTableHeader($lines, $index)) {
                $html[] = self::renderTable($lines, $index);

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/u', $line)) {
                $html[] = self::renderList($lines, $index, false);

                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/u', $line)) {
                $html[] = self::renderList($lines, $index, true);

                continue;
            }

            if (preg_match('/^>\s*(.+)$/u', $line)) {
                $html[] = self::renderQuote($lines, $index);

                continue;
            }

            $html[] = self::renderParagraph($lines, $index);
        }

        return implode('', $html);
    }

    public static function excerpt(string $text, int $limit = 140): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', str_replace("\n", ' ', strip_tags($text))) ?? '');

        if (mb_strlen($clean) <= $limit) {
            return $clean;
        }

        return rtrim(mb_substr($clean, 0, $limit - 1)).'…';
    }

    /**
     * @param  array<int, string>  $lines
     */
    private static function renderParagraph(array $lines, int &$index): string
    {
        $parts = [];
        $count = count($lines);

        while ($index < $count) {
            $line = trim($lines[$index]);

            if (
                $line === ''
                || preg_match('/^###\s+(.+)$/u', $line)
                || preg_match('/^[-*]\s+(.+)$/u', $line)
                || preg_match('/^\d+\.\s+(.+)$/u', $line)
                || preg_match('/^>\s*(.+)$/u', $line)
                || self::isTableHeader($lines, $index)
            ) {
                break;
            }

            $parts[] = $line;
            $index++;
        }

        return '<p>'.self::inline(implode(' ', $parts)).'</p>';
    }

    /**
     * @param  array<int, string>  $lines
     */
    private static function renderList(array $lines, int &$index, bool $ordered): string
    {
        $count = count($lines);
        $items = [];
        $pattern = $ordered ? '/^\d+\.\s+(.+)$/u' : '/^[-*]\s+(.+)$/u';

        while ($index < $count) {
            $line = trim($lines[$index]);
            if (! preg_match($pattern, $line, $matches)) {
                break;
            }

            $items[] = '<li>'.self::inline($matches[1]).'</li>';
            $index++;
        }

        $tag = $ordered ? 'ol' : 'ul';

        return '<'.$tag.' class="studio-rich-list">'.implode('', $items).'</'.$tag.'>';
    }

    /**
     * @param  array<int, string>  $lines
     */
    private static function renderQuote(array $lines, int &$index): string
    {
        $parts = [];
        $count = count($lines);

        while ($index < $count) {
            $line = trim($lines[$index]);
            if (! preg_match('/^>\s*(.+)$/u', $line, $matches)) {
                break;
            }

            $parts[] = $matches[1];
            $index++;
        }

        return '<blockquote class="studio-rich-quote">'.self::inline(implode(' ', $parts)).'</blockquote>';
    }

    /**
     * @param  array<int, string>  $lines
     */
    private static function renderTable(array $lines, int &$index): string
    {
        $headers = self::parseTableCells($lines[$index]);
        $index += 2;
        $rows = [];
        $count = count($lines);

        while ($index < $count) {
            $line = trim($lines[$index]);
            if ($line === '' || ! self::looksLikeTableRow($line)) {
                break;
            }

            $cells = self::parseTableCells($line);
            $rows[] = '<tr>'.implode('', array_map(
                fn (string $cell): string => '<td>'.self::inline($cell).'</td>',
                $cells
            )).'</tr>';
            $index++;
        }

        $thead = '<thead><tr>'.implode('', array_map(
            fn (string $cell): string => '<th>'.self::inline($cell).'</th>',
            $headers
        )).'</tr></thead>';

        return '<div class="studio-rich-table-wrap"><table class="studio-rich-table">'.$thead.'<tbody>'.implode('', $rows).'</tbody></table></div>';
    }

    /**
     * @param  array<int, string>  $lines
     */
    private static function isTableHeader(array $lines, int $index): bool
    {
        if (! isset($lines[$index + 1])) {
            return false;
        }

        $line = trim($lines[$index]);
        $separator = trim($lines[$index + 1]);

        return self::looksLikeTableRow($line) && preg_match('/^\|?[\s:-]+\|[\s|:-]+\|?$/u', $separator) === 1;
    }

    private static function looksLikeTableRow(string $line): bool
    {
        return str_contains($line, '|') && preg_match('/^\|?.+\|.+\|?$/u', $line) === 1;
    }

    /**
     * @return list<string>
     */
    private static function parseTableCells(string $line): array
    {
        $trimmed = trim($line);
        $trimmed = trim($trimmed, '|');
        $parts = array_map(
            fn (string $part): string => trim($part),
            explode('|', $trimmed)
        );

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }

    private static function inline(string $text): string
    {
        $escaped = e($text);

        $escaped = preg_replace('/`([^`]+)`/u', '<code>$1</code>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*([^*]+)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '<em>$1</em>', $escaped) ?? $escaped;

        return $escaped;
    }
}
