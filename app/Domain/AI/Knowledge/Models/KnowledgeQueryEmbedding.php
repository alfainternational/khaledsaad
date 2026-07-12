<?php

namespace App\Domain\AI\Knowledge\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeQueryEmbedding extends Model
{
    protected $fillable = ['scope_key', 'query_hash', 'model_name', 'model_version', 'dimensions', 'vector_json', 'expires_at'];

    protected $casts = ['dimensions' => 'integer', 'vector_json' => 'array', 'expires_at' => 'datetime'];
}
