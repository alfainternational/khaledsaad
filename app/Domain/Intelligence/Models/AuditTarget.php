<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditTarget extends Model
{
    protected $fillable = [
        'audit_run_id',
        'workspace_id',
        'project_id',
        'kind',
        'label',
        'domain',
        'page_url',
        'social_links_json',
        'status',
        'snapshot_json',
    ];

    protected $casts = [
        'social_links_json' => 'array',
        'snapshot_json' => 'array',
    ];

    public function auditRun(): BelongsTo
    {
        return $this->belongsTo(AuditRun::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(Scorecard::class);
    }
}
