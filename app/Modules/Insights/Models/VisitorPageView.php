<?php

namespace App\Modules\Insights\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مشاهدة صفحة واحدة داخل جلسة: أين وقف الزائر، وكم بقي، وإلى أين نزل.
 */
class VisitorPageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'uuid', 'session_id', 'visitor_id', 'user_id',
        'path', 'url', 'route_name', 'title', 'query_string', 'referrer',
        'status_code', 'response_ms', 'sequence', 'is_entry', 'is_exit',
        'active_seconds', 'scroll_percent', 'interactions',
        'is_bot', 'is_staff', 'is_verified', 'viewed_at', 'left_at',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'left_at' => 'datetime',
            'status_code' => 'integer',
            'response_ms' => 'integer',
            'sequence' => 'integer',
            'active_seconds' => 'integer',
            'scroll_percent' => 'integer',
            'interactions' => 'integer',
            'is_entry' => 'boolean',
            'is_exit' => 'boolean',
            'is_bot' => 'boolean',
            'is_staff' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(VisitorSession::class, 'session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VisitorEvent::class, 'page_view_id');
    }

    /** نفس تعريف الجلسة حرفيًّا — والتحقّق منها هو ما يُنسخ إلى هنا. */
    public function scopeHuman(Builder $query): Builder
    {
        $query->where('is_bot', false)->where('is_verified', true);

        if (! config('insights.count_staff')) {
            $query->where('is_staff', false);
        }

        return $query;
    }
}
