<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لقطة سحب واحدة لمنافس على منصة، مخزَّنة بحالتها ومصدرها وتاريخها.
 *
 * لا تُحذف لقطة عند سحب جديد: التاريخ هو ما يُظهر أن منافسًا كان يُعلن ثم
 * توقّف. القراءة تأخذ الأحدث لكل (منافس، منصة).
 */
class CompetitorAdSnapshot extends Model
{
    protected $fillable = [
        'project_competitor_id', 'platform', 'status',
        'source_url', 'captured_at', 'ads', 'coverage_note',
    ];

    protected function casts(): array
    {
        return [
            'ads' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function competitor(): BelongsTo
    {
        return $this->belongsTo(ProjectCompetitor::class, 'project_competitor_id');
    }
}
