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
            $text = $this->readText($file);

            if ($text === null) {
                $file->forceFill(['extraction_status' => 'unsupported', 'extracted_text' => null])->save();

                return;
            }

            $file->forceFill([
                'extraction_status' => 'completed',
                'extracted_text' => mb_substr($this->clean($text), 0, self::MAX_CHARACTERS),
            ])->save();
        } catch (Throwable $exception) {
            // فشل القراءة لا يوقف التحليل؛ الملف يُوسم ويُذكر في الافتراضات.
            $file->forceFill(['extraction_status' => 'failed', 'extracted_text' => null])->save();
        }
    }

    private function readText(ToolRunFile $file): ?string
    {
        if (in_array($file->mime_type, self::PLAIN_TEXT_MIMES, true)) {
            return Storage::disk($file->disk)->get($file->path) ?? '';
        }

        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        $absolute = $this->absolutePath($file);

        if ($absolute === null) {
            return null;
        }

        return match (true) {
            $file->mime_type === 'application/pdf' || $extension === 'pdf' => $this->readPdf($absolute),
            str_contains($file->mime_type, 'wordprocessingml') || $extension === 'docx' => $this->readWord($absolute, 'Word2007'),
            str_contains($file->mime_type, 'spreadsheetml') || in_array($extension, ['xlsx', 'xls'], true) => $this->readSpreadsheet($absolute),
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
    private function absolutePath(ToolRunFile $file): ?string
    {
        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            return null;
        }

        return $disk->path($file->path);
    }

    private function clean(string $contents): string
    {
        $normalized = preg_replace('/\R{3,}/u', "\n\n", $contents) ?? $contents;

        return trim(preg_replace('/[ \t]{2,}/u', ' ', $normalized) ?? $normalized);
    }
}
