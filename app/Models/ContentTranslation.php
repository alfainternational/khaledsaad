<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ترجمة محتوى واحد إلى لغة واحدة.
 *
 * لا تحمل `slug`: الرابط العام يبقى واحدًا لكل درس مهما تعدّدت لغاته،
 * فاللغة تُختار بالمبدّل لا بالمسار. slug لكل لغة يعني أربعين رابطًا
 * لعشرين درسًا، وانقسام أرشفة الصفحة الواحدة على نسخ متعددة.
 */
class ContentTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'content_id',
        'locale',
        'title',
        'excerpt',
        'body_html',
        'body_json',
        'seo_title',
        'seo_description',
        'source_text_hash',
        'translator',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'body_json' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * راجعها إنسان، فلا يدهسها أمر البناء.
     */
    public function isHumanReviewed(): bool
    {
        return $this->reviewed_at !== null;
    }

    /**
     * تقادمت: الأصل العربي تغيّر بعد أن تُرجم هذا السطر.
     */
    public function isStaleAgainst(string $currentSourceHash): bool
    {
        return ! hash_equals($this->source_text_hash, $currentSourceHash);
    }
}
