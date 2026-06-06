<?php

namespace App\Domain\Execution\Models;

use App\Domain\Intelligence\Models\AuditFinding;
use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recommendation extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'workspace_id',
        'project_id',
        'audit_finding_id',
        'area',
        'title',
        'priority',
        'severity',
        'evidence',
        'rationale',
        'estimated_impact',
        'confidence',
        'status',
        'created_by',
    ];

    protected $casts = [
        'priority' => 'integer',
        'confidence' => 'float',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function auditFinding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class);
    }

    public function executionPackages(): HasMany
    {
        return $this->hasMany(ExecutionPackage::class);
    }
}
