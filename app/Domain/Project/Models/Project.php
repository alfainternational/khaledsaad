<?php

namespace App\Domain\Project\Models;

use App\Domain\Intelligence\Models\AuditRun;
use App\Domain\Intelligence\Models\MonitorSnapshot;
use App\Domain\Approval\Models\Approval;
use App\Domain\Client\Models\Client;
use App\Domain\Execution\Models\ExecutionPackage;
use App\Domain\Tool\Models\ToolRun;
use App\Domain\Workspace\Models\Workspace;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasPublicId;
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'workspace_id',
        'client_id',
        'name',
        'stage',
        'status',
        'sector',
        'market_country',
        'primary_domain',
        'logo_path',
        'official_social_links_json',
        'verified_social_profiles_json',
        'competitors_json',
        'analysis_goals_json',
        'monitoring_enabled',
    ];

    protected $casts = [
        'official_social_links_json' => 'array',
        'verified_social_profiles_json' => 'array',
        'competitors_json' => 'array',
        'analysis_goals_json' => 'array',
        'monitoring_enabled' => 'boolean',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function toolRuns(): HasMany
    {
        return $this->hasMany(ToolRun::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function executionPackages(): HasMany
    {
        return $this->hasMany(ExecutionPackage::class);
    }

    public function auditRuns(): HasMany
    {
        return $this->hasMany(AuditRun::class);
    }

    public function monitorSnapshots(): HasMany
    {
        return $this->hasMany(MonitorSnapshot::class);
    }
}
