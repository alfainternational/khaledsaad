<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorSnapshot extends Model
{
    protected $fillable = [
        'workspace_id',
        'project_id',
        'audit_run_id',
        'captured_at',
        'executive_score',
        'website_score',
        'social_score',
        'seo_score',
        'trust_score',
        'conversion_score',
        'ads_readiness_score',
        'ai_visibility_score',
        'competition_score',
        'lead_readiness_score',
        'payload_json',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
        'payload_json' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function auditRun(): BelongsTo
    {
        return $this->belongsTo(AuditRun::class);
    }
}
