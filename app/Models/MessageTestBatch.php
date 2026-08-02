<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * دفعة اختبار: رسالة واحدة (single) أو رسائل الشخصيات كلها (batch).
 *
 * summary مقارنة بين الشخصيات فقط. لا تُخزَّن فيها رسالة موحّدة، لأن
 * دمج نتائج شخصيات متباينة في نصٍّ واحد يُلغي سبب وجود الاستوديو.
 */
class MessageTestBatch extends Model
{
    public const MODE_SINGLE = 'single';

    public const MODE_BATCH = 'batch';

    public const STATUS_COMPLETE = 'complete';

    public const STATUS_PARTIAL = 'partial';

    protected $fillable = [
        'project_id', 'persona_panel_id', 'user_id',
        'mode', 'channel', 'objective', 'status', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(MessageTestResult::class);
    }
}
