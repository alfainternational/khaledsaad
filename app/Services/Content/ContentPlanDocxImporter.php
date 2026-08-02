<?php

namespace App\Services\Content;

use App\Models\ContentPlan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use Throwable;

class ContentPlanDocxImporter
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(string $path): array
    {
        try {
            $document = IOFactory::load($path);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'document' => 'تعذّر قراءة ملف Word. تأكد أنه ملف DOCX سليم.',
            ]);
        }

        $paragraphs = [];
        $tables = [];

        foreach ($document->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof Table) {
                    $tables[] = $this->tableRows($element);

                    continue;
                }

                $text = $this->elementText($element);
                if ($text !== '') {
                    $paragraphs[] = $text;
                }
            }
        }

        $title = collect($paragraphs)->first(
            fn (string $text) => str_contains($text, 'خطة المحتوى الرقمي'),
        );
        $posts = [];
        $design = [];
        $publishing = [];
        $activity = [];
        $safety = [];

        foreach ($tables as $rows) {
            $first = trim((string) ($rows[0][0] ?? ''));

            if (str_starts_with($first, 'منشور')) {
                $posts[] = $this->parsePost($rows, count($posts) + 1);
            } elseif (str_contains($first, 'مقاسات X')) {
                $design = $this->keyValueRows($rows);
            } elseif (str_contains($first, 'حد الأحرف في X')) {
                $publishing = $this->keyValueRows($rows);
            } elseif ($first === 'الحالة' && str_contains((string) ($rows[0][1] ?? ''), 'الشكل')) {
                foreach (array_slice($rows, 1) as $row) {
                    if (($row[0] ?? '') !== '') {
                        $activity[] = ['الحالة' => $row[0], 'الشكل' => $row[1] ?? ''];
                    }
                }
            } elseif (preg_match('/^\d+$/u', $this->normalizeDigits($first)) === 1) {
                foreach ($rows as $row) {
                    $rule = trim((string) ($row[1] ?? ''));
                    if ($rule !== '') {
                        $safety[] = $rule;
                    }
                }
            }
        }

        if (! is_string($title) || $title === '') {
            throw ValidationException::withMessages([
                'document' => 'لم يُعثر على عنوان خطة المحتوى داخل الملف.',
            ]);
        }

        if ($posts === []) {
            throw ValidationException::withMessages([
                'document' => 'لم يُعثر على أي بطاقة تبدأ بكلمة «منشور» داخل الملف.',
            ]);
        }

        $normalizedTitle = trim($title);
        $month = $this->monthFromTitle($normalizedTitle);

        return [
            'title' => $normalizedTitle,
            'month' => $month,
            'design_specifications' => $design,
            'publishing_specifications' => $publishing,
            'activity_protocol' => $activity,
            'safety_rules' => $safety,
            'posts' => $posts,
        ];
    }

    public function import(string $path, Project $project, User $user): ContentPlan
    {
        $payload = $this->inspect($path);

        return DB::transaction(function () use ($payload, $path, $project, $user): ContentPlan {
            $plan = ContentPlan::create([
                'user_id' => $user->id,
                'project_id' => $project->id,
                'title' => $payload['title'],
                'month' => $payload['month'],
                'status' => ContentPlan::STATUS_ACTIVE,
                'source_filename' => basename($path),
                'design_specifications' => $payload['design_specifications'],
                'publishing_specifications' => $payload['publishing_specifications'],
                'activity_protocol' => $payload['activity_protocol'],
                'safety_rules' => $payload['safety_rules'],
            ]);

            foreach ($payload['posts'] as $post) {
                $plan->posts()->create($post);
            }

            return $plan->load('posts');
        });
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function tableRows(Table $table): array
    {
        $rows = [];

        foreach ($table->getRows() as $row) {
            $cells = [];
            foreach ($row->getCells() as $cell) {
                $cells[] = $this->elementsText($cell->getElements());
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $elements
     */
    private function elementsText(array $elements): string
    {
        return trim(collect($elements)
            ->map(fn (mixed $element) => $this->elementText($element))
            ->filter()
            ->implode("\n"));
    }

    private function elementText(mixed $element): string
    {
        if ($element instanceof Table) {
            return collect($this->tableRows($element))->flatten()->filter()->implode("\n");
        }

        if ($element instanceof TextRun) {
            return collect($element->getElements())
                ->map(fn (mixed $child) => $this->elementText($child))
                ->implode('');
        }

        if (method_exists($element, 'getElements')) {
            return $this->elementsText($element->getElements());
        }

        if (method_exists($element, 'getText')) {
            $text = $element->getText();

            return is_string($text) ? $text : '';
        }

        return '';
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<string, string>
     */
    private function keyValueRows(array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row[0] ?? ''));
            $value = trim((string) ($row[1] ?? ''));
            if ($key !== '' && $value !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<string, mixed>
     */
    private function parsePost(array $rows, int $fallbackPosition): array
    {
        $header = $this->compactWhitespace($this->normalizeDigits(trim((string) ($rows[0][0] ?? ''))));
        preg_match('/منشور\s*(\d+).*?(\d{1,2})\s*أغسطس\s*(\d{4}).*?·\s*([^·]+)$/u', $header, $match);

        if ($match === []) {
            throw ValidationException::withMessages([
                'document' => "تعذّر قراءة تاريخ ومحور بطاقة المنشور رقم {$fallbackPosition}.",
            ]);
        }

        $x = $this->fieldValue($rows, ['نص منشور X (تويتر)', 'نص منشور X']);
        $linkedin = $this->fieldValue($rows, ['نص منشور لينكد إن']);
        $design = $this->fieldValue($rows, ['موجز التصميم للمصمم', 'موجز التصميم  للمصمم', 'موجز التصميم']);
        $notes = $this->fieldValue($rows, ['ملاحظات النشر للناشر', 'ملاحظات النشر  للناشر', 'ملاحظات النشر']);

        if ($x === '' || $linkedin === '') {
            throw ValidationException::withMessages([
                'document' => "بطاقة المنشور رقم {$fallbackPosition} لا تحتوي نص المنصتين كاملًا.",
            ]);
        }

        preg_match_all('/#[\p{L}\p{N}_]+/u', $notes."\n".$x."\n".$linkedin, $hashtags);
        preg_match('/النص البديل\s*\(\s*Alt\s*\)\s*:\s*(.+?)(?=\s*(?:ملاحظة\s+نشر|حالة\s+التنفيذ)|$)/us', $notes, $alt);

        $position = (int) $match[1];
        $day = (int) $match[2];
        $year = (int) $match[3];
        $pillar = trim($match[4]);
        $title = $this->titleFrom($design, $x);

        return [
            'position' => $position > 0 ? $position : $fallbackPosition,
            'publish_at' => sprintf('%04d-08-%02d 09:00:00', $year, $day),
            'pillar' => $pillar,
            'title' => $title,
            'x_content' => $x,
            'linkedin_content' => $linkedin,
            'design_brief' => $design !== '' ? $design : null,
            'publishing_notes' => $notes !== '' ? $notes : null,
            'alt_text' => isset($alt[1]) ? trim($alt[1]) : null,
            'hashtags' => array_values(array_unique($hashtags[0] ?? [])),
            'requires_design' => ! str_contains($design, 'لا يحتاج تصميماً') && $design !== '',
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, string>  $labels
     */
    private function fieldValue(array $rows, array $labels): string
    {
        foreach ($rows as $index => $row) {
            $original = trim((string) ($row[0] ?? ''));
            $first = $this->compactWhitespace($original);
            $firstKey = preg_replace('/\s+/u', '', $first);

            foreach ($labels as $label) {
                $normalizedLabel = $this->compactWhitespace($label);
                $labelKey = preg_replace('/\s+/u', '', $normalizedLabel);

                if ($firstKey === $labelKey) {
                    $sameRow = trim(implode("\n", array_slice($row, 1)));
                    if ($sameRow !== '') {
                        return $sameRow;
                    }

                    $nextRow = $rows[$index + 1] ?? [];

                    return trim(implode("\n", $nextRow));
                }

                if (str_starts_with($firstKey, $labelKey)) {
                    $inline = trim(Str::after($first, $normalizedLabel), " \t\n\r\0\x0B:-");
                    $otherCells = trim(implode("\n", array_slice($row, 1)));

                    return trim($inline."\n".$otherCells);
                }
            }
        }

        return '';
    }

    private function titleFrom(string $design, string $x): string
    {
        if (preg_match('/(?:رئيسي|العنوان)\s*:\s*[«"]?([^»"\n—]+)[»"]?/u', $design, $match) === 1) {
            return Str::limit(trim($match[1]), 220, '');
        }

        $line = collect(preg_split('/\R/u', $x) ?: [])
            ->map(fn (string $value) => trim($value))
            ->first(fn (string $value) => $value !== '' && ! str_starts_with($value, '#'));

        return Str::limit($line ?: 'منشور محتوى', 220, '');
    }

    private function monthFromTitle(string $title): string
    {
        $normalized = $this->normalizeDigits($title);
        preg_match('/أغسطس\s+(\d{4})/u', $normalized, $match);

        return sprintf('%04d-08-01', isset($match[1]) ? (int) $match[1] : now()->year);
    }

    private function compactWhitespace(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
