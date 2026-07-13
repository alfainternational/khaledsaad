<?php

namespace App\Domain\AI\Web\Models;

use App\Domain\AI\Knowledge\Models\KnowledgeDocument;
use App\Domain\AI\Knowledge\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebResearchResult extends Model
{
    protected $fillable = [
        'web_research_run_id', 'knowledge_source_id', 'knowledge_document_id',
        'provider', 'rank', 'title', 'original_url', 'normalized_url',
        'normalized_url_hash', 'domain', 'snippet', 'content_hash', 'fetch_status',
        'http_status', 'trust_tier', 'trust_score', 'freshness_status',
        'verification_status', 'published_at', 'fetched_at', 'valid_until', 'meta_json',
    ];

    protected $casts = [
        'rank' => 'integer',
        'http_status' => 'integer',
        'trust_score' => 'integer',
        'published_at' => 'datetime',
        'fetched_at' => 'datetime',
        'valid_until' => 'datetime',
        'meta_json' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(WebResearchRun::class, 'web_research_run_id');
    }

    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class);
    }

    public function knowledgeDocument(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }
}
