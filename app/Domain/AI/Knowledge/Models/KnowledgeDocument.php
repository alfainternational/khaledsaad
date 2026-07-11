<?php

namespace App\Domain\AI\Knowledge\Models;

use App\Domain\AI\Knowledge\KnowledgeScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeDocument extends Model
{
    protected $fillable = [
        'knowledge_source_id',
        'content_hash',
        'version',
        'title',
        'language',
        'status',
        'content',
        'valid_from',
        'valid_until',
        'meta_json',
    ];

    protected $casts = [
        'version' => 'integer',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'meta_json' => 'array',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function scopeInScope(Builder $query, KnowledgeScope $scope): Builder
    {
        return $query->whereHas(
            'source',
            fn (Builder $sourceQuery) => $sourceQuery->inScope($scope)
        );
    }
}
