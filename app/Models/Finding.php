<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Finding extends Model
{
    protected $fillable = [
        'report_id', 'category', 'title', 'description', 'severity',
        'evidence', 'evidence_answer_id', 'evidence_quote',
        'confidence', 'is_assumption', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_assumption' => 'boolean'];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class);
    }

    public function evidenceAnswer(): BelongsTo
    {
        return $this->belongsTo(ToolRunAnswer::class, 'evidence_answer_id');
    }

    public function severityLabel(): string
    {
        return match ($this->severity) {
            'critical' => 'حرجة',
            'high' => 'عالية',
            'medium' => 'متوسطة',
            default => 'منخفضة',
        };
    }
}
