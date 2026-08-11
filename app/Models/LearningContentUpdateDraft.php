<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningContentUpdateDraft extends Model
{
    protected $fillable = [
        'content_id', 'requested_by', 'status', 'context_hash', 'summary',
        'proposed_body_html', 'changes', 'sources', 'generated_at',
    ];

    protected function casts(): array
    {
        return ['changes' => 'array', 'sources' => 'array', 'generated_at' => 'datetime'];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Content::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
