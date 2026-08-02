<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * إصدار رسالة واحد يخصّ شخصية واحدة.
 *
 * بعد الاختبار لا يُعدَّل: أي تعديل يُنشئ إصدارًا جديدًا يشير إلى أبيه،
 * فتبقى كل درجة مرتبطة بالنص الذي قيست عليه فعلًا.
 */
class MessageVariant extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_TESTED = 'tested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ARCHIVED = 'archived';

    public const ORIGIN_SUGGESTED = 'suggested';

    public const ORIGIN_MANUAL = 'manual';

    public const ORIGIN_REVISED = 'revised';

    protected $fillable = [
        'project_id', 'persona_panel_id', 'user_id', 'persona_key',
        'channel', 'objective', 'content', 'origin', 'status', 'parent_id',
        'source_type', 'source_id', 'source_context', 'teaching_note', 'reusable_formula',
    ];

    protected function casts(): array
    {
        return [
            'source_context' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function panel(): BelongsTo
    {
        return $this->belongsTo(PersonaPanel::class, 'persona_panel_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(MessageTestResult::class);
    }

    /**
     * النص المختبَر لا يُكتب فوقه — التحرير مسموح على المسودة وحدها.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function latestScore(): ?int
    {
        return $this->results()->latest('id')->value('score');
    }
}
