<?php

namespace App\Domain\Intelligence\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Workspace\Models\Workspace;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AuditRun extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'workspace_id',
        'project_id',
        'status',
        'trigger_source',
        'started_at',
        'completed_at',
        'failed_at',
        'summary_json',
        'report_json',
        'payload_json',
        'error_json',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'summary_json' => 'array',
        'report_json' => 'array',
        'payload_json' => 'array',
        'error_json' => 'array',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function targets(): HasMany
    {
        return $this->hasMany(AuditTarget::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function scorecards(): HasMany
    {
        return $this->hasMany(Scorecard::class);
    }

    public function officialContacts(): HasMany
    {
        return $this->hasMany(OfficialContact::class);
    }
}
