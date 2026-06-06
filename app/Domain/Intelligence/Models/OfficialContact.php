<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficialContact extends Model
{
    protected $fillable = [
        'audit_run_id',
        'audit_target_id',
        'workspace_id',
        'project_id',
        'contact_type',
        'contact_value',
        'source_url',
        'is_verified',
        'is_primary',
        'meta_json',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'is_primary' => 'boolean',
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
