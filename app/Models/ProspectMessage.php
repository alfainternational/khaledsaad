<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * رسالة موجّهة إلى عميل متوقع بعينه.
 *
 * بلا درجة ولا رد متوقَّع: لا أحد يستطيع محاكاة رأي إنسان مُسمّى، ورقمٌ
 * بجانب اسمه يُقرأ كأنه استُطلع فعلًا.
 */
class ProspectMessage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ARCHIVED = 'archived';

    public const ORIGIN_GENERATED = 'generated';

    public const ORIGIN_MANUAL = 'manual';

    protected $fillable = [
        'prospect_id', 'project_id', 'user_id', 'channel', 'objective',
        'content', 'why', 'origin', 'status', 'parent_id',
        'source_type', 'source_id', 'source_context', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'source_context' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'أُرسلت',
            self::STATUS_ARCHIVED => 'مؤرشفة',
            default => 'جاهزة للإرسال',
        };
    }
}
