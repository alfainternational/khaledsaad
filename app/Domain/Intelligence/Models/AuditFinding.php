<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditFinding extends Model
{
    protected $fillable = [
        'audit_run_id',
        'audit_target_id',
        'workspace_id',
        'project_id',
        'area',
        'subcategory',
        'severity',
        'confidence',
        'score_impact',
        'title',
        'evidence',
        'recommendation',
        'source_url',
        'meta_json',
    ];

    protected $casts = [
        'confidence' => 'float',
        'meta_json' => 'array',
    ];

    public function auditRun(): BelongsTo
    {
        return $this->belongsTo(AuditRun::class);
    }

    public function auditTarget(): BelongsTo
    {
        return $this->belongsTo(AuditTarget::class);
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
