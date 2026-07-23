<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectCompetitor extends Model
{
    // مصدر المنافس: من عرفه.
    public const SOURCE_NAMED = 'named';       // سمّاه المستخدم — يقين

    public const SOURCE_SUGGESTED = 'suggested'; // اقترحناه — مرشّح

    // طبقة المنافس بحسب أثره على المستخدم.
    public const TIER_LOCAL = 'local';

    public const TIER_REGIONAL = 'regional';

    public const TIER_GLOBAL = 'global';

    // حالة المرشّح.
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANDIDATE = 'candidate';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'project_id', 'name', 'source', 'tier', 'status', 'url', 'strengths', 'weaknesses', 'note',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeCandidates(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CANDIDATE);
    }

    public function scopeLocal(Builder $query): Builder
    {
        return $query->where('tier', self::TIER_LOCAL);
    }

    /**
     * ترتيب الأثر: المحلي أولًا، فالإقليمي، فالعالمي.
     */
    public function tierWeight(): int
    {
        return match ($this->tier) {
            self::TIER_LOCAL => 0,
            self::TIER_REGIONAL => 1,
            default => 2,
        };
    }
}
