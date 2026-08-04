<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentMedia extends Model
{
    protected $table = 'content_media';

    protected $fillable = [
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'alt_text',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return route('content.media.show', $this);
    }

    public function humanReadableSize(): string
    {
        $bytes = max(0, (int) $this->size_bytes);

        if ($bytes < 1024) {
            return $bytes.' بايت';
        }

        if ($bytes < 1024 * 1024) {
            return $this->formatSize($bytes / 1024).' كيلوبايت';
        }

        return $this->formatSize($bytes / 1024 / 1024).' ميجابايت';
    }

    private function formatSize(float $size): string
    {
        return rtrim(rtrim(number_format($size, 1, '.', ''), '0'), '.');
    }
}
