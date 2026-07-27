<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ConsultationSession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVIEW = 'review';

    public const STATUS_QUEUED = 'analysis_queued';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['uuid', 'project_id', 'user_id', 'guest_session_id', 'blueprint_version_id', 'status', 'depth', 'actual_stage', 'current_question_version_id', 'questions_answered', 'confidence', 'scope_snapshot', 'confirmed_at', 'completed_at', 'last_activity_at'];

    protected function casts(): array
    {
        return ['scope_snapshot' => 'array', 'confirmed_at' => 'datetime', 'completed_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $session) => $session->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blueprintVersion(): BelongsTo
    {
        return $this->belongsTo(ConsultationBlueprintVersion::class, 'blueprint_version_id');
    }

    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(QuestionVersion::class, 'current_question_version_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ConsultationAnswer::class);
    }

    public function moduleStates(): HasMany
    {
        return $this->hasMany(ConsultationModuleState::class);
    }

    public function inferences(): HasMany
    {
        return $this->hasMany(ConsultationInference::class);
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(ConsultationConflict::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ConsultationEvidence::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConsultationEvent::class);
    }

    public function agencyReport(): HasOne
    {
        return $this->hasOne(AgencyReport::class);
    }

    public function toolRuns(): HasMany
    {
        return $this->hasMany(ToolRun::class);
    }
}
