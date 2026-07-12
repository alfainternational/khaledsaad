<?php

namespace App\Domain\AI\Knowledge\Models;

use App\Domain\AI\Knowledge\KnowledgeScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'knowledge_document_id',
        'position',
        'heading',
        'content',
        'token_count',
        'locator_json',
    ];

    protected $casts = [
        'position' => 'integer',
        'token_count' => 'integer',
        'locator_json' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    public function embeddings(): HasMany
    {
        return $this->hasMany(KnowledgeEmbedding::class, 'knowledge_chunk_id');
    }

    public function scopeInScope(Builder $query, KnowledgeScope $scope): Builder
    {
        return $query->whereHas(
            'document.source',
            fn (Builder $sourceQuery) => $sourceQuery->inScope($scope)
        );
    }
}
