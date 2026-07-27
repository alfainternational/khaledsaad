<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'name', 'slug', 'industry', 'stage', 'status', 'latest_score',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(ProjectProfile::class);
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(ProjectAudience::class);
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(ProjectCompetitor::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(ToolRun::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function agencyReports(): HasMany
    {
        return $this->hasMany(AgencyReport::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function kpis(): HasMany
    {
        return $this->hasMany(Kpi::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ProjectAnswer::class);
    }

    public function consultationSessions(): HasMany
    {
        return $this->hasMany(ConsultationSession::class);
    }

    public function pulseDigests(): HasMany
    {
        return $this->hasMany(PulseDigest::class)->latest('week_start');
    }

    public function geoPack(): HasOne
    {
        return $this->hasOne(GeoPack::class);
    }

    public function personaPanel(): HasOne
    {
        return $this->hasOne(PersonaPanel::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
