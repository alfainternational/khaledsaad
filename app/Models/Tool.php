<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tool extends Model
{
    use HasFactory;

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_COMING_SOON = 'coming_soon';

    protected $fillable = [
        'key', 'name', 'title', 'description', 'pain', 'promise', 'audience', 'duration_minutes',
        'category', 'status', 'current_version_id', 'sort_order',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(ToolVersion::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(ToolVersion::class, 'current_version_id');
    }

    public function isRunnable(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->current_version_id !== null;
    }

    public function scopeRunnable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('current_version_id');
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
