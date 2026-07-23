<?php

namespace App\Services\Tools;

use App\Models\ToolRun;
use App\Models\ToolRunFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * رفع أدلة المستخدم إلى تشغيل معيّن.
 *
 * الملفات تُخزَّن على قرص خاص (local) لا في public: مرفقات العميل ليست عامة،
 * وتُقرأ عند التحليل عبر رابط داخلي فقط.
 */
class AttachmentUploader
{
    private const DISK = 'local';

    private const MAX_KILOBYTES = 10240; // 10MB

    private const MAX_FILES_PER_RUN = 8;

    /**
     * @var array<int, string>
     */
    private const ALLOWED = [
        'pdf', 'docx', 'xlsx', 'xls', 'csv', 'txt', 'md', 'json',
        'jpg', 'jpeg', 'png',
    ];

    public function store(ToolRun $run, UploadedFile $file): ToolRunFile
    {
        $this->guard($run, $file);

        $path = $file->store("tool-runs/{$run->uuid}", self::DISK);

        return $run->files()->create([
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'extraction_status' => 'pending',
        ]);
    }

    public function delete(ToolRunFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }

    private function guard(ToolRun $run, UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED, true)) {
            throw ValidationException::withMessages([
                'file' => "نوع الملف .{$extension} غير مدعوم. المدعوم: ".implode('، ', self::ALLOWED).'.',
            ]);
        }

        if ($file->getSize() > self::MAX_KILOBYTES * 1024) {
            throw ValidationException::withMessages([
                'file' => 'حجم الملف يتجاوز الحد المسموح (10 ميغابايت).',
            ]);
        }

        if ($run->files()->count() >= self::MAX_FILES_PER_RUN) {
            throw ValidationException::withMessages([
                'file' => 'بلغت الحد الأقصى للمرفقات ('.self::MAX_FILES_PER_RUN.' ملفات).',
            ]);
        }
    }

    /**
     * قواعد التحقق للاستخدام في الطلبات.
     *
     * @return array<int, string>
     */
    public static function validationRules(): array
    {
        return ['required', 'file', 'max:'.self::MAX_KILOBYTES, 'mimes:'.implode(',', self::ALLOWED)];
    }
}
