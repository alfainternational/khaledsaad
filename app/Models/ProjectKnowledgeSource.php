<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectKnowledgeSource extends Model
{
    protected $fillable = [
        'project_id', 'field_key', 'value_json', 'value_hash', 'event_type',
        'source_type', 'source_key', 'source_id', 'confidence', 'period',
        'metadata', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value_json' => 'array',
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function value(): mixed
    {
        return $this->value_json['value'] ?? null;
    }
}
