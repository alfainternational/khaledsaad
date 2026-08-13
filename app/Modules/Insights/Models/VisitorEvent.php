<?php

namespace App\Modules\Insights\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * فعلٌ واحد داخل صفحة: نقرة، إرسال نموذج، تنزيل، خروج إلى موقع آخر.
 *
 * هذا ما يحوّل «زار ثلاث صفحات» إلى «زار ثلاثًا وضغط زر التسعير مرتين
 * ولم يكمل النموذج» — والفرق بينهما هو الفرق بين رقم وقرار.
 */
class VisitorEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id', 'page_view_id', 'visitor_id', 'user_id',
        'name', 'category', 'label', 'path', 'value', 'meta',
        'is_staff', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'value' => 'float',
            'is_staff' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VisitorSession::class, 'session_id');
    }

    public function pageView(): BelongsTo
    {
        return $this->belongsTo(VisitorPageView::class, 'page_view_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeHuman(Builder $query): Builder
    {
        if (! config('insights.count_staff')) {
            $query->where('is_staff', false);
        }

        return $query;
    }

    /** هل هذا الحدث تحويل بحسب قائمة config (لا بحسب حكم في الكود). */
    public function isConversion(): bool
    {
        return array_key_exists($this->name, (array) config('insights.conversion_events', []));
    }
}
