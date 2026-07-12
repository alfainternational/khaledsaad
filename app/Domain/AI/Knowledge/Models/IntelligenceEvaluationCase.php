<?php

namespace App\Domain\AI\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntelligenceEvaluationCase extends Model
{
    protected $fillable = ['public_id', 'scope_key', 'account_id', 'workspace_id', 'project_id', 'visibility', 'query', 'expected_chunk_id', 'expected_source_uri', 'minimum_rank', 'status', 'meta_json'];

    protected $casts = ['account_id' => 'integer', 'workspace_id' => 'integer', 'project_id' => 'integer', 'expected_chunk_id' => 'integer', 'minimum_rank' => 'integer', 'meta_json' => 'array'];

    public function expectedChunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'expected_chunk_id');
    }
}
