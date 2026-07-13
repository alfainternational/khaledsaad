<?php

namespace App\Domain\AI\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeEmbedding extends Model
{
    protected $fillable = ['knowledge_chunk_id', 'model_name', 'model_version', 'dimensions', 'content_hash', 'vector_json', 'status'];

    protected $casts = ['knowledge_chunk_id' => 'integer', 'dimensions' => 'integer', 'vector_json' => 'array'];

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'knowledge_chunk_id');
    }
}
