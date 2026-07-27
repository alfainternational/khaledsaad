<?php

namespace App\Services\Tools;

use App\Models\ToolRun;
use App\Models\ToolRunFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetReader;
use PhpOffice\PhpWord\IOFactory as WordReader;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * استخراج نص المرفقات قبل إرسالها للتحليل.
 *
 * يدعم النص الصريح وPDF وDOCX وXLSX. ما لا يُدعم يُوسم unsupported بوضوح
 * بدل تمرير محتوى فارغ يجعل النموذج يخترع ما ليس في الملف.
 */
class AttachmentExtractor
{
    private const PLAIN_TEXT_MIMES = [
        'text/plain', 'text/csv', 'text/markdown', 'application/json', 'text/html',
    ];

    private const MAX_CHARACTERS = 20000;

    public function extractAll(ToolRun $run): void
    {
        foreach ($run->files()->where('extraction_status', 'pending')->get() as $file) {
            $this->extract($file);
        }
    }

    public function extract(ToolRunFile $file): void
    {
        try {
            $file->forceFill($this->extractStoredFile(
                $file->disk,
                $file->path,
                $file->mime_type,
                $file->original_name,
            ))->save();
        } catch (Throwable $exception) {
            // فشل القراءة لا يوقف التحليل؛ الملف يُوسم ويُذكر في الافتراضات.
            $file->forceFill(['extraction_status' => 'failed', 'extracted_text' => null])->save();
        }
    }

    /** @return array{extraction_status:string,extracted_text:?string} */
    public function extractStoredFile(string $disk, string $path, string $mimeType, string $originalName): array
    {
        try {
            $text = $this->readText($disk, $path, $mimeType, $originalName);

            return $text === null
                ? ['extraction_status' => 'unsupported', 'extracted_text' => null]
                : [
                    'extraction_status' => 'completed',
                    'extracted_text' => mb_substr($this->clean($text), 0, self::MAX_CHARACTERS),
                ];
        } catch (Throwable) {
            return ['extraction_status' => 'failed', 'extracted_text' => null];
        }
    }

    private function readText(string $diskName, string $path, string $mimeType, string $originalName): ?string
    {
        if (in_array($mimeType, self::PLAIN_TEXT_MIMES, true)) {
            return Storage::disk($diskName)->get($path) ?? '';
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $absolute = $this->absolutePath($diskName, $path);

        if ($absolute === null) {
            return null;
        }

        return match (true) {
            $mimeType === 'application/pdf' || $extension === 'pdf' => $this->readPdf($absolute),
            str_contains($mimeType, 'wordprocessingml') || $extension === 'docx' => $this->readWord($absolute, 'Word2007'),
            str_contains($mimeType, 'spreadsheetml') || in_array($extension, ['xlsx', 'xls'], true) => $this->readSpreadsheet($absolute),
            default => null,
        };
    }

    private function readPdf(string $path): string
    {
        return (new PdfParser)->parseFile($path)->getText();
    }

    private function readWord(string $path, string $reader): string
    {
        $document = WordReader::load($path, $reader);
        $text = '';

        foreach ($document->getSections() as $section) {
            $text .= $this->wordElementText($section->getElements());
        }

        return $text;
    }

    /**
     * @param  array<int, mixed>  $elements
     */
    private function wordElementText(array $elements): string
    {
        $text = '';

        foreach ($elements as $element) {
            if (method_exists($element, 'getText')) {
                $value = $element->getText();
                $text .= (is_string($value) ? $value : '').' ';
            }

            if (method_exists($element, 'getElements')) {
                $text .= $this->wordElementText($element->getElements());
            }

            if (method_exists($element, 'getText') || method_exists($element, 'getElements')) {
                $text .= "\n";
            }
        }

        return $text;
    }

    private function readSpreadsheet(string $path): string
    {
        $spreadsheet = SpreadsheetReader::load($path);
        $text = '';

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->toArray(null, true, false, false) as $row) {
                $cells = array_filter(array_map(fn ($cell) => trim((string) $cell), $row), fn ($c) => $c !== '');

                if ($cells !== []) {
                    $text .= implode(' | ', $cells)."\n";
                }
            }
        }

        return $text;
    }

    /**
     * dompdf وpdfparser يحتاجان مسارًا حقيقيًا على القرص لا محتوى في الذاكرة.
     */
    private function absolutePath(string $diskName, string $path): ?string
    {
        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->path($path);
    }

    private function clean(string $contents): string
    {
        $normalized = preg_replace('/\R{3,}/u', "\n\n", $contents) ?? $contents;

        return trim(preg_replace('/[ \t]{2,}/u', ' ', $normalized) ?? $normalized);
    }
}
