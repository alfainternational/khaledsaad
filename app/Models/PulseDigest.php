<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * خلاصة النبض الأسبوعي لمشروع واحد: ما تغيّر هذا الأسبوع وما الخطوة التالية.
 */
class PulseDigest extends Model
{
    protected $fillable = [
        'workspace_id', 'project_id', 'week_start', 'items', 'next_step',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'items' => 'array',
            'next_step' => 'array',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
